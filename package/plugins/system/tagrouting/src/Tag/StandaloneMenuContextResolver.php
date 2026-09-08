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

    public function find(string $language): ?StandaloneMenuContext
    {
        return $this->selector->best($this->contexts(), $language);
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

            if (($query['view'] ?? null) !== 'tags') {
                continue;
            }

            $params = method_exists($item, 'getParams') ? $item->getParams() : new Registry($item->params ?? '');
            $contexts[] = new StandaloneMenuContext(
                (int) $item->id,
                (string) ($item->route ?? ''),
                (string) ($item->language ?? '*'),
                (bool) (int) ($params instanceof Registry ? $params->get('use_menu_route_canonical', 1) : 1),
            );
        }

        return $this->contexts = $contexts;
    }
}
