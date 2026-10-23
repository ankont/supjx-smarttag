<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Tag;

final readonly class StandaloneMenuContext
{
    /** @var list<string> */
    public array $routeSegments;

    /** @var list<int> */
    public array $contentTypes;

    public function __construct(
        public int $itemId,
        string $route,
        public string $language,
        public bool $useMenuRouteAsCanonical,
        public bool $bindRootMarker = true,
        array $contentTypes = [],
    ) {
        $this->contentTypes = ContentTypeScope::normalize($contentTypes);
        $this->routeSegments = array_values(array_filter(explode('/', trim($route, '/')), 'strlen'));
    }

    public function handlesRootMarker(): bool
    {
        // Without a distinct menu namespace, standalone links still need the marker.
        return $this->contentTypes === [] && ($this->bindRootMarker || $this->routeSegments === []);
    }

    public function usesMenuRoute(): bool
    {
        return $this->routeSegments !== []
            && ($this->contentTypes !== [] || $this->useMenuRouteAsCanonical || !$this->handlesRootMarker());
    }

    public function route(): string
    {
        return implode('/', $this->routeSegments);
    }
}
