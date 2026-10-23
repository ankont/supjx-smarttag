<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Tags\Site\Model;

use Joomla\CMS\Factory;
use SuperSoft\Plugin\System\TagRouting\Routing\TagPageState;
use SuperSoft\Plugin\System\TagRouting\Tag\ContentTypeScope;
use SuperSoft\Plugin\System\TagRouting\Tags\PublicationQueryGuard;

/** Revalidates native content against its live record, not only the UCM copy. */
final class TagModel extends \Joomla\Component\Tags\Site\Model\TagModel
{
    protected function populateState($ordering = 'c.core_title', $direction = 'ASC')
    {
        parent::populateState($ordering, $direction);
        $app = Factory::getApplication();
        $input = $app->getInput();
        $menu = $app->getMenu()->getActive();

        if ($input->getCmd('option') !== 'com_tags' || $input->getCmd('view') !== 'tag'
            || TagPageState::isUnboundMarker($app->getMenu())
            || ($menu->query['option'] ?? null) !== 'com_tagrouting'
            || ($menu->query['view'] ?? null) !== 'tags') {
            return;
        }

        $types = ContentTypeScope::normalize($menu->getParams()->get('content_types', []));
        $params = clone $this->state->get('params');
        $params->set('supjx_content_types', $types);
        $params->set('supjx_single_content_type', count($types) === 1);
        $this->setState('params', $params);
        if ($types !== []) {
            // Menu namespace is authoritative; query types cannot widen its scope.
            $this->setState('tag.typesr', $types);
        }
        if ($input->getWord('format') === 'feed') {
            return;
        }

        $stored = $menu->getParams()->get('tag_page_limit', 30);
        // Joomla's integer form filter may store an empty numeric field as 0.
        $limit = trim((string) $stored) === '' || (int) $stored === 0
            ? max(0, (int) $app->get('list_limit', 20))
            : ((int) $stored > 0 ? min(500, (int) $stored) : 30);
        $explicit = $this->explicitRequestLimit();
        if ($explicit !== null) {
            $limit = $explicit;
        }
        $this->setState('list.limit', $limit);
        // getState() would re-enter lazy populateState() before it completes.
        $params = clone $this->state->get('params');
        $params->set('display_num', $limit);
        // URL overrides are supported, so retain the native selector setting.
        // Do not mutate global/shared component params.
        $this->setState('params', $params);
    }

    protected function getListQuery()
    {
        $query = parent::getListQuery();
        $db = $this->getDatabase();
        $query->where(PublicationQueryGuard::condition(
            $db->quote('com_content.article'),
            $db->quote('com_content.category'),
            $db->quote(Factory::getDate()->toSql()),
            $db->quote($db->getNullDate()),
            array_map('intval', $this->getCurrentUser()->getAuthorisedViewLevels()),
        ));

        return $query;
    }

    public function getPagination()
    {
        $pagination = parent::getPagination();
        $input = Factory::getApplication()->getInput();
        $explicit = $this->explicitRequestLimit();
        if ($explicit !== null) {
            $pagination->setAdditionalUrlParam('limit', $explicit);
        }
        foreach (['cat-view', 'lang'] as $key) {
            $value = $key === 'cat-view' ? (string) $input->get->get($key, '', 'cmd') : $input->getCmd($key);
            if ($value !== '') {
                $pagination->setAdditionalUrlParam($key, $value);
            }
        }

        return $pagination;
    }

    private function explicitRequestLimit(): ?int
    {
        $input = Factory::getApplication()->getInput();
        $raw = $input->get->get('limit', null, 'raw');
        if ($raw === null) {
            // The native limit selector can submit a POST form. This is also
            // explicit input, unlike the shared global.list.limit session value.
            $raw = $input->post->get('limit', null, 'raw');
        }
        if (!is_string($raw) && !is_int($raw)) {
            return null;
        }
        $value = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

        return $value === false ? null : $value;
    }
}
