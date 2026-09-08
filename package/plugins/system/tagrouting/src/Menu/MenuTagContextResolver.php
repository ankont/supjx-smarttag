<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Menu;

use Joomla\CMS\Menu\AbstractMenu;
use Joomla\Registry\Registry;
use SuperSoft\Plugin\System\TagRouting\Domain\SerializedExpression;

final class MenuTagContextResolver
{
    public function __construct(
        private readonly AbstractMenu $menu,
        private readonly LogicalFilterState $states,
    ) {
    }

    /**
     * Selects a menu item only for an exact logical state. Ordering of tags and
     * AND groups is deliberately ignored; presentation/sort parameters are not.
     *
     * @param array<string,mixed> $query
     */
    public function canonicalize(array &$query): bool
    {
        $option = (string) ($query['option'] ?? '');

        if (!in_array($option, ['com_content', 'com_tags'], true)) {
            return false;
        }

        $isTags = $option === 'com_tags';
        $requested = $this->states->from($query, $isTags);

        if ($requested['groups'] === [] && $requested['extra'] === []) {
            return false;
        }

        $language = (string) ($query['lang'] ?? '*');
        $items = $this->menu->getItems('component', $option) ?: [];
        usort($items, static fn (object $a, object $b): int => (int) $a->id <=> (int) $b->id);

        foreach ($items as $item) {
            if (!in_array((string) $item->language, ['*', $language], true) && $language !== '*') {
                continue;
            }

            $itemValues = array_merge(
                $item->query ?? [],
                $item->getParams() instanceof Registry ? $item->getParams()->toArray() : [],
            );

            if (!$this->sameBase($query, $itemValues, $isTags)) {
                continue;
            }

            if ($this->states->from($itemValues, $isTags) !== $requested) {
                continue;
            }

            $query['Itemid'] = (int) $item->id;

            foreach ([SerializedExpression::REQUEST_KEY, 'filter_tag', 'tags', 'tag_ids', 'types', 'tag_list_language_filter', 'tag_list_all', 'match_all'] as $key) {
                unset($query[$key]);
            }

            return true;
        }

        return false;
    }

    /** @param array<string,mixed> $query @param array<string,mixed> $item */
    private function sameBase(array $query, array $item, bool $isTags): bool
    {
        if (($item['option'] ?? $query['option'] ?? null) !== ($query['option'] ?? null)) {
            return false;
        }

        if (isset($query['view'], $item['view']) && $query['view'] !== $item['view']) {
            return false;
        }

        if (!$isTags && isset($query['id'], $item['id']) && (int) $query['id'] !== (int) $item['id']) {
            return false;
        }

        return true;
    }
}
