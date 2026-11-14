<?php
declare(strict_types=1);
namespace SuperSoft\Plugin\System\TagRouting\Tag;

use Joomla\Database\DatabaseInterface;
use SuperSoft\Plugin\System\TagRouting\Configuration\FacetLimits;
use SuperSoft\Plugin\System\TagRouting\Domain\FacetExpressionParser;
use SuperSoft\Plugin\System\TagRouting\Domain\FacetExpressionCanonicalizer;
use SuperSoft\Plugin\System\TagRouting\Domain\FacetMembership;
use SuperSoft\Plugin\System\TagRouting\Domain\TagPathResolver;
use SuperSoft\Plugin\System\TagRouting\Infrastructure\CategoryTaxonomyResolver;

final readonly class StandaloneFacetResolver
{
    public function __construct(private object $app, private DatabaseInterface $db) {}

    public function scope(int $itemId, string $language): array
    {
        if (!\SuperSoft\Plugin\System\TagRouting\Configuration\RoutingFeatures::fromPlugin()->hierarchicalTags) {
            throw new \InvalidArgumentException('Standalone tag routing is disabled.');
        }
        $languages = \Joomla\CMS\Language\LanguageHelper::getLanguages('sef');
        $language = $languages[$language]->lang_code ?? ($language === '*' ? $this->app->getLanguage()->getTag() : $language);
        $item = $this->app->getMenu()->getItem($itemId);
        $params = $item?->getParams();
        if (($item->component ?? $item->query['option'] ?? null) !== 'com_tagrouting'
            || !in_array((string) ($item->language ?? '*'), ['*', $language], true)
            || !in_array((int) ($item->access ?? 1), array_map('intval', $this->app->getIdentity()->getAuthorisedViewLevels()), true)
            || !(bool) (int) $params?->get('enable_tag_facets', 0)
            || !(bool) (int) $params?->get('use_menu_route_canonical', 1)
            || trim((string) ($item->route ?? ''), '/') === '') {
            throw new \InvalidArgumentException('Additional tag filters require an enabled canonical SmartTag menu namespace.');
        }
        $all = $params->get('tag_facet_scope', 'selected') === 'all';
        return CategoryTaxonomyResolver::forApplication($this->app, $this->db)->resolveRoots(
            $all ? null : ContentTypeScope::normalize($params->get('tag_facet_roots', [])),
            array_values($this->app->getIdentity()->getAuthorisedViewLevels()), $language);
    }

    public function resolve(int $tagId, int $itemId, string $expression, string $language): array
    {
        // Limits apply to raw input before any lookup or canonical deduplication.
        $parsed = (new FacetExpressionParser(FacetLimits::fromPlugin()))->parse($expression);
        if ($parsed === null) { throw new \InvalidArgumentException('Invalid or over-limit tag expression.'); }
        $scope = $this->scope($itemId, $language);
        $resolved = (new TagPathResolver())->resolve($parsed, $scope['nodes']);
        if ($resolved === null) { throw new \InvalidArgumentException('Tag filter outside permitted menu scope.'); }
        $canonical = (new FacetExpressionCanonicalizer())->normalize($resolved, $scope['nodes']);
        $ids = $canonical->tags()->ids();
        $params = $this->app->getMenu()->getItem($itemId)->getParams();
        return ['tag'=>$tagId, 'item'=>$itemId, 'expression'=>$canonical->expression(), 'selected'=>$ids,
            'groups'=>FacetMembership::groups($ids, $scope['nodes'], \SuperSoft\Plugin\System\TagRouting\Configuration\RoutingOptions::descendants($params->get('tag_facet_descendants', ''))),
            'resolved'=>$canonical, 'scope'=>$scope];
    }
}
