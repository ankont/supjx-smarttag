<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Site\Service;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Categories\CategoryFactoryInterface;
use Joomla\CMS\Component\Router\Rules\MenuRules;
use Joomla\CMS\Component\Router\Rules\NomenuRules;
use Joomla\CMS\Menu\AbstractMenu;
use Joomla\Database\DatabaseInterface;
use SuperSoft\Plugin\System\TagRouting\Configuration\RoutingFeatures;
use SuperSoft\Plugin\System\TagRouting\Domain\FacetExpressionParser;
use SuperSoft\Plugin\System\TagRouting\Domain\SerializedExpression;
use SuperSoft\Plugin\System\TagRouting\Domain\TagExpressionNormalizer;
use SuperSoft\Plugin\System\TagRouting\Domain\TagPathResolver;
use SuperSoft\Plugin\System\TagRouting\Infrastructure\CategoryTaxonomyResolver;
use SuperSoft\Plugin\System\TagRouting\Menu\LogicalFilterState;
use SuperSoft\Plugin\System\TagRouting\Menu\MenuTagContextResolver;
use SuperSoft\Plugin\System\TagRouting\Routing\CanonicalMenuRules;
use SuperSoft\Plugin\System\TagRouting\Routing\FacetDelimiter;
use SuperSoft\Plugin\System\TagRouting\Routing\FacetedBuildRule;
use SuperSoft\Plugin\System\TagRouting\Routing\FacetedParseRule;

final class Router extends \Joomla\Component\Content\Site\Service\Router
{
    public function __construct(
        SiteApplication $app,
        AbstractMenu $menu,
        CategoryFactoryInterface $categoryFactory,
        DatabaseInterface $db,
    ) {
        parent::__construct($app, $menu, $categoryFactory, $db);

        $features = RoutingFeatures::fromPlugin();
        $serializer = new SerializedExpression();
        $contexts = $features->canonicalCleanup
            ? new MenuTagContextResolver($menu, new LogicalFilterState($serializer, $features->facetedCategories))
            : null;
        $ordered = [];

        foreach ($this->getRules() as $rule) {
            $this->detachRule($rule);

            if ($features->canonicalCleanup && $rule instanceof MenuRules) {
                $ordered[] = new CanonicalMenuRules($this, $contexts);
            } else {
                $ordered[] = $rule;
            }
        }

        foreach ($ordered as $rule) {
            $this->attachRule($rule);

            if ($features->facetedCategories && $rule instanceof NomenuRules) {
                $delimiter = new FacetDelimiter($features->acceptedMarkers, $features->canonicalMarker);
                // Both StandardRules and NomenuRules must build the real category
                // path before virtual segments are appended.
                $this->attachRule(new FacetedBuildRule($serializer, $delimiter));
                $this->attachRule(new FacetedParseRule(
                    $app,
                    new CategoryTaxonomyResolver($db),
                    new FacetExpressionParser(),
                    new TagPathResolver(),
                    new TagExpressionNormalizer(),
                    $serializer,
                    $delimiter,
                ));
            }
        }
    }

    public function getName()
    {
        return 'content';
    }
}
