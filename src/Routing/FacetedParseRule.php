<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Routing;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Component\Router\Rules\RulesInterface;
use SuperSoft\Plugin\System\TagRouting\Domain\SerializedExpression;
use SuperSoft\Plugin\System\TagRouting\Domain\TagExpressionNormalizer;
use SuperSoft\Plugin\System\TagRouting\Domain\TagPathResolver;
use SuperSoft\Plugin\System\TagRouting\Infrastructure\CategoryTaxonomyResolver;

final readonly class FacetedParseRule implements RulesInterface
{
    public function __construct(
        private SiteApplication $app,
        private CategoryTaxonomyResolver $taxonomies,
        private TagPathResolver $paths,
        private TagExpressionNormalizer $normalizer,
        private SerializedExpression $serializer,
        private FacetDelimiter $delimiter,
    ) {
    }

    public function preprocess(&$query)
    {
    }

    public function build(&$query, &$segments)
    {
    }

    public function parse(&$segments, &$vars)
    {
        if ($segments === [] || ($vars['view'] ?? null) !== 'category') {
            return;
        }

        $delimiter = $this->delimiter->extract(array_values($segments));

        if ($delimiter === null) {
            // Bare tag paths are intentionally unsupported. Anything before the
            // marker belongs exclusively to Joomla's real component route.
            return;
        }

        $categoryId = (int) ($vars['id'] ?? 0);

        if ($categoryId < 1) {
            return;
        }

        $user = $this->app->getIdentity();
        $scope = $this->taxonomies->resolve(
            $categoryId,
            array_values($user->getAuthorisedViewLevels()),
            $this->app->getLanguage()->getTag(),
        );

        if ($scope['roots'] === []) {
            return;
        }

        $public = $this->paths->resolve($delimiter['segments'], $scope['nodes']);

        if ($public === null) {
            // Leaving the segments untouched delegates failure to Joomla's normal
            // route-not-found handling instead of guessing an ambiguous tag.
            return;
        }

        $effective = $this->normalizer->normalize($public);

        if ($effective->isEmpty()) {
            return;
        }

        $vars[SerializedExpression::REQUEST_KEY] = $this->serializer->encode($categoryId, $public, $effective);

        if (!$delimiter['canonical']) {
            // Joomla's SEF plugin rebuilds tainted parsed variables and issues the
            // canonical redirect; FacetedBuildRule always emits the short marker.
            $this->app->getRouter()->setTainted();
        }

        while ($segments !== []) {
            array_shift($segments);
        }
    }
}
