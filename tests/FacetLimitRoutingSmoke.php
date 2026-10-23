<?php

declare(strict_types=1);

namespace Joomla\CMS\Component\Router\Rules { interface RulesInterface {} }
namespace Joomla\CMS\Application {
    class SiteApplication {
        public function getIdentity() { throw new \RuntimeException('Limit check ran too late.'); }
    }
}
namespace Joomla\CMS\Language { class Text { public static function _($key) { return $key; } } }
namespace Joomla\CMS\Router\Exception { class RouteNotFoundException extends \RuntimeException {} }
namespace SuperSoft\Plugin\System\TagRouting\Infrastructure {
    class CategoryTaxonomyResolver {
        public function resolve(...$args) { throw new \RuntimeException('Taxonomy queried for invalid request.'); }
    }
}
namespace {
    require __DIR__ . '/run.php';
    $rule = new \SuperSoft\Plugin\System\TagRouting\Routing\FacetedParseRule(
        new \Joomla\CMS\Application\SiteApplication(),
        new \SuperSoft\Plugin\System\TagRouting\Infrastructure\CategoryTaxonomyResolver(),
        new \SuperSoft\Plugin\System\TagRouting\Domain\FacetExpressionParser(new \SuperSoft\Plugin\System\TagRouting\Configuration\FacetLimits()),
        new \SuperSoft\Plugin\System\TagRouting\Domain\TagPathResolver(),
        new \SuperSoft\Plugin\System\TagRouting\Domain\TagExpressionNormalizer(),
        new \SuperSoft\Plugin\System\TagRouting\Domain\SerializedExpression(),
        new \SuperSoft\Plugin\System\TagRouting\Routing\FacetDelimiter(),
    );
    foreach (['!', '!tag', '!filter'] as $marker) {
        $segments = [$marker, implode('+', array_fill(0, 11, 'a'))];
        $vars = ['view' => 'category', 'id' => 9];
        try {
            $rule->parse($segments, $vars);
            throw new \RuntimeException('Over-limit route did not return 404.');
        } catch (\Joomla\CMS\Router\Exception\RouteNotFoundException) {
            if (isset($vars['__tagrouting'])) { throw new \RuntimeException('Rejected route acquired filter state.'); }
        }
    }
    $buildRule = new \SuperSoft\Plugin\System\TagRouting\Routing\FacetedBuildRule(
        new \SuperSoft\Plugin\System\TagRouting\Domain\SerializedExpression(),
        new \SuperSoft\Plugin\System\TagRouting\Routing\FacetDelimiter(),
        new \SuperSoft\Plugin\System\TagRouting\Configuration\FacetLimits(1, 1, 1),
    );
    $node = new \SuperSoft\Plugin\System\TagRouting\Domain\TagNode(2, 'a', 1, 1, 2, 1);
    $expr = new \SuperSoft\Plugin\System\TagRouting\Domain\TagExpression([
        new \SuperSoft\Plugin\System\TagRouting\Domain\TagGroup([$node]),
        new \SuperSoft\Plugin\System\TagRouting\Domain\TagGroup([$node]),
    ]);
    $query = ['__tagrouting' => (new \SuperSoft\Plugin\System\TagRouting\Domain\SerializedExpression())->encode(9, $expr, $expr)];
    $segments = [];
    try {
        $buildRule->build($query, $segments);
        throw new \RuntimeException('Direct serialized build bypassed configured limits.');
    } catch (\InvalidArgumentException) {
        if ($segments !== []) { throw new \RuntimeException('Over-limit build appended route segments.'); }
    }
    echo "OK (all marker aliases reject before taxonomy access; direct builds enforce limits)\n";
}
