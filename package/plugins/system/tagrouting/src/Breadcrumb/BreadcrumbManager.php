<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Breadcrumb;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Router\Route;
use SuperSoft\Plugin\System\TagRouting\Configuration\RoutingFeatures;
use SuperSoft\Plugin\System\TagRouting\Routing\FacetedRequest;
use SuperSoft\Plugin\System\TagRouting\Routing\TagPageState;
use SuperSoft\Plugin\System\TagRouting\Routing\TagFilterRequest;
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
        if ($this->applyFaceted()) {
            return;
        }

        if ($this->features->hierarchicalTags) {
            $this->applyStandalone();
        }
    }

    private function applyFaceted(): bool
    {
        $state = FacetedRequest::current();
        if ($state === null) {
            return false;
        }

        $query = ['option' => 'com_content', 'view' => 'category', 'id' => $state['category']];
        $itemId = $this->app->getInput()->getInt('Itemid');
        if ($itemId > 0) {
            $query['Itemid'] = $itemId;
        }
        $pathway = $this->app->getPathway();
        $items = $pathway->getPathway();
        $last = array_key_last($items);
        $category = $this->app->bootComponent('com_content')->getCategory()->get($state['category']);
        $baseLink = Route::_('index.php?' . http_build_query($query), false);
        if ($category !== null && $category !== false) {
            $title = htmlspecialchars($category->title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            if ($last !== null && in_array($items[$last]->name, [$category->title, $title], true)) {
                $items[$last]->link = $baseLink;
                $pathway->setPathway($items);
            } else {
                $pathway->addItem($title, $baseLink);
            }
        }
        return $this->appendFilters($state['groups']);
    }

    private function appendFilters(array $selection): bool
    {
        $levels = array_values($this->app->getIdentity()->getAuthorisedViewLevels());
        $language = $this->app->getLanguage()->getTag();
        $labels = [];
        $groups = [];

        foreach ($selection as $group) {
            $choices = [];
            foreach ($group as $id) {
                if (!isset($labels[$id])) {
                    $resolved = $this->tags->pathForId($id, $levels, $language);
                    if ($resolved === null) {
                        return false;
                    }
                    $labels[$id] = implode(' › ', array_map(
                        static fn ($node): string => htmlspecialchars(
                            ($rootMarker && $inline && $marker !== '' && $index === $last ? $marker . ' ' : '')
                    . ($node->title !== '' ? $node->title : $node->alias),
                            ENT_QUOTES | ENT_SUBSTITUTE,
                            'UTF-8',
                        ),
                        $resolved->path,
                    ));
                }
                $choices[] = $labels[$id];
            }
            $groups[] = implode(', ', $choices);
        }

        $this->appendSummary(implode(' &amp; ', $groups));

        return true;
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
        $menu = $this->app->getMenu();
        $rootMarker = TagPageState::usesRootMarker($menu);
        $inline = $this->features->breadcrumbMarkerPlacement === 'inline';
        $marker = $this->features->breadcrumbMarker;
        if ($rootMarker && !$inline && $marker !== '') {
            $this->app->getPathway()->addItem($marker);
        }
        $filters = TagFilterRequest::current();
        $last = array_key_last($resolved->path);

        foreach ($resolved->path as $index => $node) {
            $this->app->getPathway()->addItem(
                ($rootMarker && $inline && $marker !== '' && $index === $last ? $marker . ' ' : '')
                    . ($node->title !== '' ? $node->title : $node->alias),
                // This is navigation inside the current request language. An
                // explicit lang would leak into monolingual URLs when Joomla's
                // Language Filter plugin is disabled.
                $index === $last && $filters === null ? '' : TagRoute::forTag($node->id, null, $filters['item'] ?? null),
            );
        }

        if ($filters !== null) {
            $this->appendFilters($filters['selected']);
        }

        return true;
    }

    private function appendSummary(string $label): void
    {
        $marker = $this->features->breadcrumbMarker;
        if ($marker !== '') {
            if ($this->features->breadcrumbMarkerPlacement === 'inline') {
                $label = $marker . ' ' . $label;
            } else {
                $this->app->getPathway()->addItem($marker);
            }
        }
        $this->app->getPathway()->addItem($label);
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
