<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Breadcrumb;

use Joomla\CMS\Application\SiteApplication;
use SuperSoft\Plugin\System\TagRouting\Configuration\RoutingFeatures;
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
    ) {
    }

    public function apply(): void
    {
        if ($this->features->hierarchicalTags) {
            $this->applyStandalone();
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
                // This is navigation inside the current request language. An
                // explicit lang would leak into monolingual URLs when Joomla's
                // Language Filter plugin is disabled.
                $index === $last ? '' : TagRoute::forTag($node->id),
            );
        }

        return true;
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
