<?php
declare(strict_types=1);
namespace SuperSoft\Plugin\System\TagRouting\Configuration;


final readonly class FacetSettings
{
    public function __construct(public string $scope = 'category', public bool $includeDescendants = false) {}

    public static function fromPlugin(): self
    {
        $params = RoutingOptions::get();
        $scope = (string) $params->get('facet_scope', 'category');
        return new self(in_array($scope, ['category', 'inherited', 'all'], true) ? $scope : 'category',
            (bool) (int) $params->get('facet_include_descendants', 0));
    }
}
