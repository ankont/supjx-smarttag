<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Tag;

use Joomla\CMS\Menu\AbstractMenu;
use Joomla\Registry\Registry;

final class StandaloneMenuContextResolver
{
    /** @var list<StandaloneMenuContext>|null */
    private ?array $contexts = null;

    public function __construct(
        private readonly AbstractMenu $menu,
        private readonly StandaloneMenuSelector $selector = new StandaloneMenuSelector(),
    ) {
    }

    public function find(string $language, array $types = [], ?int $preferredItemId = null): ?StandaloneMenuContext
    {
        return $this->selector->best($this->contexts(), $language, $types, $preferredItemId);
    }

    /** @param list<string> $segments */
    public function match(array $segments, string $language): ?StandaloneMenuContext
    {
        return $this->selector->match($segments, $this->contexts(), $language);
    }

    /** @return list<StandaloneMenuContext> */
    private function contexts(): array
    {
        if ($this->contexts !== null) {
            return $this->contexts;
        }

        $contexts = [];

        foreach ($this->menu->getItems('component', 'com_tagrouting') ?: [] as $item) {
            $query = is_array($item->query ?? null) ? $item->query : [];

            // Virtual root temporarily delegates through the native tag view
            // while the real menu component and namespace remain SmartTag.
            $delegatedRoot = ($item->component ?? null) === 'com_tagrouting'
                && ($query['option'] ?? null) === 'com_tags'
                && ($query['view'] ?? null) === 'tag'
                && empty($query['id']);
            if (($query['view'] ?? null) !== 'tags' && !$delegatedRoot) {
                continue;
            }

            $params = method_exists($item, 'getParams') ? $item->getParams() : new Registry($item->params ?? '');
            $contexts[] = new StandaloneMenuContext(
                (int) $item->id,
                (string) ($item->route ?? ''),
                (string) ($item->language ?? '*'),
                (bool) (int) ($params instanceof Registry ? $params->get('use_menu_route_canonical', 1) : 1),
                (bool) (int) ($params instanceof Registry ? $params->get('bind_root_marker', 1) : 1),
                ContentTypeScope::normalize($params instanceof Registry ? $params->get('content_types', []) : []),
            );
        }

        return $this->contexts = $contexts;
    }
}
