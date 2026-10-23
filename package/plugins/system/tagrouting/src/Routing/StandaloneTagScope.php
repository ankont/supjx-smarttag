<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Routing;

use Joomla\CMS\Menu\AbstractMenu;
use SuperSoft\Plugin\System\TagRouting\Tag\ContentTypeScope;

/** Capture native query/menu semantics before com_tags substitutes an Itemid. */
final class StandaloneTagScope
{
    /** @return array{types:list<int>,item:?int} */
    public static function capture(AbstractMenu $menu, array $query): array
    {
        $item = isset($query['Itemid']) ? $menu->getItem((int) $query['Itemid']) : null;
        $active = $menu->getActive();
        $preferred = null;
        if (array_key_exists('types', $query)) {
            $types = ContentTypeScope::normalize($query['types']);
        } elseif ((($item->query['option'] ?? null) === 'com_tagrouting' || ($item->component ?? null) === 'com_tagrouting')) {
            $types = ContentTypeScope::normalize($item->getParams()->get('content_types', []));
            $preferred = (int) $item->id;
        } elseif (ContentTypeScope::normalize($item->query['types'] ?? []) !== []) {
            $types = ContentTypeScope::normalize($item->query['types']);
        } elseif (TagPageState::isUnboundMarker($menu)) {
            $types = [];
        } elseif ((($active->query['option'] ?? null) === 'com_tagrouting' || ($active->component ?? null) === 'com_tagrouting')) {
            $types = ContentTypeScope::normalize($active->getParams()->get('content_types', []));
            $preferred = (int) $active->id;
        } else {
            $types = [];
        }
        return ['types' => $types, 'item' => $preferred];
    }
}
