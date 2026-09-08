<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Tag;

final readonly class StandaloneMenuContext
{
    /** @var list<string> */
    public array $routeSegments;

    public function __construct(
        public int $itemId,
        string $route,
        public string $language,
        public bool $useMenuRouteAsCanonical,
    ) {
        $this->routeSegments = array_values(array_filter(explode('/', trim($route, '/')), 'strlen'));
    }

    public function route(): string
    {
        return implode('/', $this->routeSegments);
    }
}
