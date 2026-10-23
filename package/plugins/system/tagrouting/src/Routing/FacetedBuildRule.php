<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Routing;

use Joomla\CMS\Component\Router\Rules\RulesInterface;
use SuperSoft\Plugin\System\TagRouting\Domain\SerializedExpression;
use SuperSoft\Plugin\System\TagRouting\Configuration\FacetLimits;
use SuperSoft\Plugin\System\TagRouting\Domain\FacetExpressionParser;
use SuperSoft\Plugin\System\TagRouting\Domain\FacetExpressionCanonicalizer;
use SuperSoft\Plugin\System\TagRouting\Domain\TagPathResolver;
use SuperSoft\Plugin\System\TagRouting\Infrastructure\CategoryTaxonomyResolver;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Language\LanguageHelper;

final readonly class FacetedBuildRule implements RulesInterface
{
    public function __construct(
        private SerializedExpression $serializer,
        private FacetDelimiter $delimiter,
        private ?FacetLimits $limits = null,
        private ?CategoryTaxonomyResolver $taxonomies = null,
        private ?SiteApplication $app = null,
    ) {
    }

    public function preprocess(&$query)
    {
    }

    public function parse(&$segments, &$vars)
    {
    }

    public function build(&$query, &$segments)
    {
        $state = $this->serializer->decode($query[SerializedExpression::REQUEST_KEY] ?? null);

        if ($state === null) {
            return;
        }

        if ($this->limits !== null && !$this->limits->allows($state['expression'])) {
            throw new \InvalidArgumentException('Faceted expression exceeds the configured limits.');
        }

        // StandardRules may already have consumed these keys. If they remain,
        // however, they must still describe the serialized category route.
        if (isset($query['view']) && $query['view'] !== 'category') {
            return;
        }

        if (isset($query['id']) && (int) $query['id'] !== $state['category']) {
            return;
        }

        $canonical = (new FacetExpressionParser())->parse($state['expression'])->canonical;
        if ($this->taxonomies !== null && $this->app !== null) {
            $language = $this->app->getLanguage()->getTag();
            $requestedLanguage = (string) ($query['lang'] ?? '');
            if ($requestedLanguage !== '' && $requestedLanguage !== '*' && $requestedLanguage !== $language) {
                $languages = LanguageHelper::getLanguages('sef');
                $language = isset($languages[$requestedLanguage]) ? $languages[$requestedLanguage]->lang_code : $requestedLanguage;
            }
            $scope = $this->taxonomies->resolve(
                $state['category'],
                array_values($this->app->getIdentity()->getAuthorisedViewLevels()),
                $language,
            );
            $resolved = (new TagPathResolver())->resolve((new FacetExpressionParser())->parse($state['expression']), $scope['nodes']);
            if ($resolved === null) {
                throw new \InvalidArgumentException('Faceted expression is outside the current taxonomy scope.');
            }
            $canonical = (new FacetExpressionCanonicalizer())->normalize($resolved, $scope['nodes'])->expression();
        }
        foreach ($this->delimiter->canonicalSegments(explode('/', $canonical)) as $segment) {
            $segments[] = $segment;
        }

        unset($query[SerializedExpression::REQUEST_KEY]);
    }
}
