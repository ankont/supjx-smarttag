<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Breadcrumb;

use Joomla\CMS\Application\SiteApplication;
use SuperSoft\Plugin\System\TagRouting\Configuration\RoutingFeatures;
use SuperSoft\Plugin\System\TagRouting\Domain\FacetExpression;
use SuperSoft\Plugin\System\TagRouting\Domain\FacetExpressionParser;
use SuperSoft\Plugin\System\TagRouting\Domain\FacetGroup;
use SuperSoft\Plugin\System\TagRouting\Domain\SerializedExpression;
use SuperSoft\Plugin\System\TagRouting\Domain\TagNode;
use SuperSoft\Plugin\System\TagRouting\Domain\TagPath;
use SuperSoft\Plugin\System\TagRouting\Domain\TagPathResolver;
use SuperSoft\Plugin\System\TagRouting\Infrastructure\CategoryTaxonomyResolver;
use SuperSoft\Plugin\System\TagRouting\Routing\FacetedRoute;
use SuperSoft\Plugin\System\TagRouting\Routing\TagPageState;
use SuperSoft\Plugin\System\TagRouting\Routing\TagRoute;
use SuperSoft\Plugin\System\TagRouting\Tag\ResolvedTagPage;
use SuperSoft\Plugin\System\TagRouting\Tag\TagHierarchyRepository;

final readonly class BreadcrumbManager
{
    public function __construct(
        private SiteApplication $app,
        private RoutingFeatures $features,
        private TagHierarchyRepository $tags,
        private CategoryTaxonomyResolver $taxonomies,
    ) {
    }

    public function apply(): void
    {
        if ($this->features->hierarchicalTags && $this->applyStandalone()) {
            return;
        }

        if ($this->features->facetedCategories) {
            $this->applyFaceted();
        }
    }

    private function applyStandalone(): bool
    {
        $tagId = TagPageState::id($this->app->getInput()->get(TagPageState::REQUEST_KEY));

        if ($tagId === null || $this->app->getInput()->getCmd('option') !== 'com_tags') {
            return false;
        }

        $resolved = $this->tags->pathForId(
            $tagId,
            array_values($this->app->getIdentity()->getAuthorisedViewLevels()),
            $this->app->getLanguage()->getTag(),
        );

        if ($resolved === null) {
            return false;
        }

        $this->removeCoreLeaf($resolved);
        $this->addMarker();
        $last = array_key_last($resolved->path);

        foreach ($resolved->path as $index => $node) {
            $this->app->getPathway()->addItem(
                $node->title !== '' ? $node->title : $node->alias,
                $index === $last ? '' : TagRoute::forTag($node->id, $this->app->getLanguage()->getTag()),
            );
        }

        return true;
    }

    private function applyFaceted(): void
    {
        $state = (new SerializedExpression())->decode(
            $this->app->getInput()->getString(SerializedExpression::REQUEST_KEY),
        );

        if (
            $state === null
            || $this->app->getInput()->getCmd('option') !== 'com_content'
            || $this->app->getInput()->getCmd('view') !== 'category'
        ) {
            return;
        }

        $scope = $this->taxonomies->resolve(
            $state['category'],
            array_values($this->app->getIdentity()->getAuthorisedViewLevels()),
            $this->app->getLanguage()->getTag(),
        );
        $parsed = (new FacetExpressionParser())->parse($state['expression']);
        $expression = $parsed === null ? null : (new TagPathResolver())->resolve($parsed, $scope['nodes']);

        // Breadcrumbs are linear. A compound AND/OR expression has no honest
        // single ancestor chain, so leave Joomla's category pathway untouched.
        if ($expression === null || count($expression->groups) !== 1 || count($expression->groups[0]->paths) !== 1) {
            return;
        }

        $path = $expression->groups[0]->paths[0]->tags;
        $this->addMarker();
        $last = array_key_last($path);
        $active = $this->app->getMenu()->getActive();
        $itemId = $active !== null && ($active->query['option'] ?? null) === 'com_content' ? (int) $active->id : null;

        foreach ($path as $index => $node) {
            $partial = new FacetExpression([new FacetGroup([new TagPath(array_slice($path, 0, $index + 1))])]);
            $this->app->getPathway()->addItem(
                $node->title !== '' ? $node->title : $node->alias,
                $index === $last ? '' : FacetedRoute::get($state['category'], $partial, $itemId),
            );
        }
    }

    private function addMarker(): void
    {
        if ($this->features->breadcrumbMarker !== '') {
            $this->app->getPathway()->addItem($this->features->breadcrumbMarker);
        }
    }

    private function removeCoreLeaf(ResolvedTagPage $resolved): void
    {
        $items = $this->app->getPathway()->getPathway();
        $last = $items[array_key_last($items)] ?? null;
        $leaf = $resolved->path[array_key_last($resolved->path)];
        $title = $leaf->title !== '' ? $leaf->title : $leaf->alias;

        if (is_object($last) && (string) ($last->name ?? '') === $title) {
            array_pop($items);
            $this->app->getPathway()->setPathway($items);
        }
    }
}
