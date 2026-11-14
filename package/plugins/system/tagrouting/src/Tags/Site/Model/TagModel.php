<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Tags\Site\Model;

use Joomla\CMS\Factory;
use SuperSoft\Plugin\System\TagRouting\Routing\TagFilterRequest;
use SuperSoft\Plugin\System\TagRouting\Tags\TagQueryFilter;
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
        $filter = TagQueryFilter::fromQuery([
            'content-types' => $input->get->get('content-types', null, 'raw'),
            'content-category' => $input->get->get('content-category', null, 'raw'),
        ]);
        $this->setState('supjx.query_filter', $filter);
        $params = clone $this->state->get('params');
        $params->set('supjx_query_filters', $filter->parameters());
        if ($filter->category !== null || count($filter->types) === 1) {
            $params->set('supjx_single_content_type', true);
        }
        $this->setState('params', $params);

        if ($input->getCmd('option') !== 'com_tags' || $input->getCmd('view') !== 'tag'
            || TagPageState::isUnboundMarker($app->getMenu())
            || ($menu->query['option'] ?? null) !== 'com_tagrouting'
            || ($menu->query['view'] ?? null) !== 'tags') {
            return;
        }

        $groupCategories = (bool) (int) $menu->getParams()->get('group_article_categories', 0);
        $this->setState('supjx.group_categories', $groupCategories);
        $params = clone $this->state->get('params');
        $params->set('supjx_group_article_categories', $groupCategories);
        $this->setState('params', $params);
        $types = ContentTypeScope::normalize($menu->getParams()->get('content_types', []));
        $params = clone $this->state->get('params');
        $params->set('supjx_content_types', $types);
        $params->set('supjx_single_content_type', ($filter->category !== null || count($filter->types) === 1) || count($types) === 1);
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

        $filter = $this->getState('supjx.query_filter');
        if ($filter instanceof TagQueryFilter) {
            $filter->apply($query, $db,
                array_map('intval', $this->getCurrentUser()->getAuthorisedViewLevels()),
                Factory::getApplication()->getLanguage()->getTag());
        }

        $extra = TagFilterRequest::current();
        if ($extra !== null) {
            // Re-resolve under the current identity/language before extending SQL.
            $extra = (new \SuperSoft\Plugin\System\TagRouting\Tag\StandaloneFacetResolver(Factory::getApplication(), $db))
                ->resolve($extra['tag'], $extra['item'], $extra['expression'], Factory::getApplication()->getLanguage()->getTag());
            foreach ($extra['groups'] as $index => $ids) {
                $alias = 'supjx_extra' . $index;
                $query->where('EXISTS (SELECT 1 FROM #__contentitem_tag_map AS ' . $alias
                    . ' WHERE ' . $alias . '.content_item_id = m.content_item_id'
                    . ' AND ' . $alias . '.type_alias = m.type_alias'
                    . ' AND ' . $alias . '.tag_id IN (' . implode(',', $ids) . '))');
            }
        }
        if ($this->getState('supjx.group_categories')) {
            $query->where("m.type_alias <> 'com_content.category'")
                ->leftJoin('#__content AS supjx_group_article', "m.type_alias = 'com_content.article' AND supjx_group_article.id = m.content_item_id")
                ->leftJoin('#__categories AS supjx_group_category', 'supjx_group_category.id = supjx_group_article.catid')
                ->select('MAX(supjx_group_category.id) AS supjx_category_id, MAX(supjx_group_category.title) AS supjx_category_title');
            $ordering = $query->order?->getElements() ?? [];
            $query->clear('order')->order([
                "CASE WHEN m.type_alias = 'com_content.article' THEN 0 ELSE 1 END",
                'm.type_alias ASC', 'supjx_category_title ASC', 'supjx_category_id ASC',
            ]);
            if ($ordering !== []) { $query->order($ordering); }
            $query->order('m.content_item_id ASC');
        }
        return $query;
    }

    public function getItems()
    {
        $items = parent::getItems();
        if ($this->getState('supjx.group_categories')) {
            foreach ($items as $item) {
                $article = $item->type_alias === 'com_content.article';
                $item->supjx_group_key = $article ? 'com_content.category:' . (int) $item->supjx_category_id : $item->type_alias;
                $item->supjx_group_title = $article ? $item->supjx_category_title : ($item->content_type_title ?? $item->type_title ?? $item->type_alias);
            }
        }
        return $items;
    }

    /** Presentation adapter, one shared pagination; native getItems() stays flat. */
    public function getGroupedItems(): array
    {
        $groups = [];
        foreach ($this->getItems() as $item) {
            $key = $item->supjx_group_key ?? $item->type_alias;
            $groups[$key] ??= ['key'=>$key, 'title'=>$item->supjx_group_title ?? $item->type_alias, 'items'=>[]];
            $groups[$key]['items'][] = $item;
        }
        return array_values($groups);
    }

    protected function getStoreId($id = '')
    {
        $filter = $this->getState('supjx.query_filter');
        return parent::getStoreId($id . ':' . json_encode([$filter?->parameters() ?? [], $this->getState('supjx.group_categories'), TagFilterRequest::current()['expression'] ?? null]));
    }

    public function getPagination()
    {
        $pagination = parent::getPagination();
        $input = Factory::getApplication()->getInput();
        $extra = TagFilterRequest::current();
        if ($extra !== null) { $pagination->setAdditionalUrlParam(TagFilterRequest::KEY, TagFilterRequest::encode($extra)); }
        $explicit = $this->explicitRequestLimit();
        if ($explicit !== null) {
            $pagination->setAdditionalUrlParam('limit', $explicit);
        }
        $filter = $this->getState('supjx.query_filter');
        foreach ($filter?->parameters() ?? [] as $key => $value) {
            $pagination->setAdditionalUrlParam($key, $value);
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
