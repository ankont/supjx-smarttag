<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Routing;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Component\Router\Rules\RulesInterface;
use Joomla\CMS\Router\Exception\RouteNotFoundException;
use Joomla\CMS\Language\Text;
use SuperSoft\Plugin\System\TagRouting\Domain\FacetExpressionParser;
use SuperSoft\Plugin\System\TagRouting\Domain\SerializedExpression;
use SuperSoft\Plugin\System\TagRouting\Domain\TagExpressionNormalizer;
use SuperSoft\Plugin\System\TagRouting\Domain\TagPathResolver;
use SuperSoft\Plugin\System\TagRouting\Domain\FacetExpressionCanonicalizer;
use SuperSoft\Plugin\System\TagRouting\Infrastructure\CategoryTaxonomyResolver;

final readonly class FacetedParseRule implements RulesInterface
{
    public function __construct(
        private SiteApplication $app,
        private CategoryTaxonomyResolver $taxonomies,
        private FacetExpressionParser $expressions,
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

        $rawExpression = implode('/', $delimiter['segments']);
        $parsed = $this->expressions->parse($rawExpression);

        if ($parsed === null) {
            throw new RouteNotFoundException(Text::_('JERROR_PAGE_NOT_FOUND'));
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

        $facets = $this->paths->resolve($parsed, $scope['nodes']);

        if ($facets === null) {
            // Leaving the segments untouched delegates failure to Joomla's normal
            // route-not-found handling instead of guessing an ambiguous tag.
            return;
        }

        $facets = (new FacetExpressionCanonicalizer())->normalize($facets, $scope['nodes']);
        $public = $facets->tags();
        $effective = $this->normalizer->normalize($public);

        if ($effective->isEmpty()) {
            return;
        }

        $vars[SerializedExpression::REQUEST_KEY] = $this->serializer->encode(
            $categoryId,
            $public,
            $effective,
            $facets->expression(),
        );
        FacetedRequest::remember($this->app, $vars[SerializedExpression::REQUEST_KEY]);

        if (!$delimiter['canonical'] || str_replace(' ', '+', trim($rawExpression)) !== $facets->expression()) {
            // Joomla's SEF plugin rebuilds tainted parsed variables and issues the
            // canonical redirect; FacetedBuildRule always emits the short marker.
            $this->app->getRouter()->setTainted();
        }

        while ($segments !== []) {
            array_shift($segments);
        }
    }
}
