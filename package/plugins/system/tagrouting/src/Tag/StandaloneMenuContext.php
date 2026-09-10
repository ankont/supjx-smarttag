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
        public bool $bindRootMarker = true,
    ) {
        $this->routeSegments = array_values(array_filter(explode('/', trim($route, '/')), 'strlen'));
    }

    public function handlesRootMarker(): bool
    {
        // Without a distinct menu namespace, standalone links still need the marker.
        return $this->bindRootMarker || $this->routeSegments === [];
    }

    public function usesMenuRoute(): bool
    {
        return $this->routeSegments !== []
            && ($this->useMenuRouteAsCanonical || !$this->handlesRootMarker());
    }

    public function route(): string
    {
        return implode('/', $this->routeSegments);
    }
}
