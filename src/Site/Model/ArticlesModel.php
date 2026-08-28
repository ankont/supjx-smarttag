<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Site\Model;

use Joomla\CMS\Factory;
use SuperSoft\Plugin\System\TagRouting\Domain\SerializedExpression;
use SuperSoft\Plugin\System\TagRouting\Infrastructure\CategoryTaxonomyResolver;

final class ArticlesModel extends \Joomla\Component\Content\Site\Model\ArticlesModel
{
    protected function getListQuery()
    {
        $query = parent::getListQuery();
        $input = Factory::getApplication()->getInput();
        $state = (new SerializedExpression())->decode($input->getString(SerializedExpression::REQUEST_KEY));
        $categoryState = $this->getState('filter.category_id');
        $categoryIds = is_array($categoryState) ? array_map('intval', $categoryState) : [(int) $categoryState];

        if (
            $state === null
            || $input->getCmd('option') !== 'com_content'
            || $input->getCmd('view') !== 'category'
            || !in_array($state['category'], $categoryIds, true)
        ) {
            return $query;
        }

        $db = $this->getDatabase();
        $app = Factory::getApplication();
        $scope = (new CategoryTaxonomyResolver($db))->resolve(
            $state['category'],
            array_values($app->getIdentity()->getAuthorisedViewLevels()),
            $app->getLanguage()->getTag(),
        );
        $allowed = array_fill_keys(array_map(static fn ($node): int => $node->id, $scope['nodes']), true);

        foreach ($state['groups'] as $tagIds) {
            foreach ($tagIds as $tagId) {
                if (!isset($allowed[$tagId])) {
                    // Never trust a namespaced query variable supplied directly by
                    // a client; re-check category scope, access and language here.
                    return $query;
                }
            }
        }

        foreach ($state['groups'] as $index => $tagIds) {
            // One EXISTS per group gives AND between groups; IN gives OR inside
            // each group. IDs were resolved server-side and revalidated on decode.
            $alias = 'tagrouting' . $index;
            $subQuery = $db->createQuery()
                ->select('1')
                ->from($db->quoteName('#__contentitem_tag_map', $alias))
                ->where($db->quoteName($alias . '.content_item_id') . ' = ' . $db->quoteName('a.id'))
                ->where($db->quoteName($alias . '.type_alias') . ' = ' . $db->quote('com_content.article'))
                ->where($db->quoteName($alias . '.tag_id') . ' IN (' . implode(',', $tagIds) . ')');

            $query->where('EXISTS (' . $subQuery . ')');
        }

        return $query;
    }
}
