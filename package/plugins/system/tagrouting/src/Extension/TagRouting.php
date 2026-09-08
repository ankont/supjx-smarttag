<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Extension;

use Joomla\CMS\Event\Application\AfterInitialiseEvent;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\RouterFactory;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\SiteRouter;
use Joomla\CMS\Router\SiteRouterAwareInterface;
use Joomla\CMS\Router\SiteRouterAwareTrait;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use SuperSoft\Plugin\System\TagRouting\Configuration\RoutingFeatures;
use SuperSoft\Plugin\System\TagRouting\Breadcrumb\BreadcrumbManager;
use SuperSoft\Plugin\System\TagRouting\Domain\SerializedExpression;
use SuperSoft\Plugin\System\TagRouting\Infrastructure\CategoryTaxonomyResolver;
use SuperSoft\Plugin\System\TagRouting\Menu\LogicalFilterState;
use SuperSoft\Plugin\System\TagRouting\Menu\MenuTagContextResolver;
use SuperSoft\Plugin\System\TagRouting\Routing\GenericMenuBuildRule;
use SuperSoft\Plugin\System\TagRouting\Routing\FacetDelimiter;
use SuperSoft\Plugin\System\TagRouting\Routing\StandaloneTagBuildRule;
use SuperSoft\Plugin\System\TagRouting\Routing\StandaloneTagParseRule;
use SuperSoft\Plugin\System\TagRouting\Routing\StandaloneTagPrepareBuildRule;
use SuperSoft\Plugin\System\TagRouting\Service\FacetedMVCFactoryProvider;
use SuperSoft\Plugin\System\TagRouting\Tag\TagHierarchyRepository;
use SuperSoft\Plugin\System\TagRouting\Tag\TagHierarchyResolver;
use SuperSoft\Plugin\System\TagRouting\Tag\StandaloneMenuContextResolver;
use SuperSoft\Plugin\System\TagRouting\Tag\StandaloneMenuSelector;

final class TagRouting extends CMSPlugin implements SubscriberInterface, SiteRouterAwareInterface
{
    use SiteRouterAwareTrait;

    private ?TagHierarchyRepository $tagRepository = null;
    private ?CategoryTaxonomyResolver $taxonomyRepository = null;

    public static function getSubscribedEvents(): array
    {
        return [
            'onAfterExtensionBoot' => 'onAfterExtensionBoot',
            'onAfterInitialise' => 'onAfterInitialise',
            'onAfterDispatch' => 'onAfterDispatch',
        ];
    }

    public function onAfterExtensionBoot(Event $event): void
    {
        if (!$this->getApplication()->isClient('site')) {
            return;
        }

        $features = RoutingFeatures::fromRegistry($this->params);

        if (!$features->needsContentRouter()) {
            return;
        }

        [, $type, $extensionName, $container] = array_values($event->getArguments());

        if ($type !== ComponentInterface::class || $extensionName !== 'content') {
            return;
        }

        $namespace = '\\SuperSoft\\Plugin\\System\\TagRouting';
        $container->registerServiceProvider(new RouterFactory($namespace));

        if ($features->facetedCategories) {
            $container->registerServiceProvider(new FacetedMVCFactoryProvider($namespace));
        }
    }

    public function onAfterInitialise(AfterInitialiseEvent $event): void
    {
        $app = $event->getApplication();

        if (!$app->isClient('site')) {
            return;
        }

        $features = RoutingFeatures::fromRegistry($this->params);

        if (!$features->needsSiteRules()) {
            return;
        }

        $router = $this->getSiteRouter();

        if ($features->hierarchicalTags) {
            $repository = $this->tags();
            $menus = new StandaloneMenuContextResolver($app->getMenu(), new StandaloneMenuSelector());
            $markers = new FacetDelimiter($features->acceptedMarkers, $features->canonicalMarker);

            // Capture the tag ID before cleanup or the core com_tags router can
            // consume it. This rule only marks single-tag page builds.
            $router->attachBuildRule(new StandaloneTagPrepareBuildRule(), SiteRouter::PROCESS_BEFORE);
            $router->attachParseRule(
                new StandaloneTagParseRule(
                    $app,
                    $repository,
                    $menus,
                    $markers,
                ),
                SiteRouter::PROCESS_BEFORE,
            );
            // The component route has already been built at PROCESS_DURING;
            // this rule replaces only explicitly marked tag-page routes.
            $router->attachBuildRule(
                new StandaloneTagBuildRule($app, $repository, $menus, $markers),
                SiteRouter::PROCESS_DURING,
            );
        }

        if ($features->canonicalCleanup) {
            $contexts = new MenuTagContextResolver(
                $app->getMenu(),
                new LogicalFilterState(new SerializedExpression(), $features->facetedCategories),
            );

            // This also covers com_tags without replacing its component router.
            $router->attachBuildRule(new GenericMenuBuildRule($contexts), SiteRouter::PROCESS_BEFORE);
        }
    }

    public function onAfterDispatch(Event $event): void
    {
        $app = $this->getApplication();

        if (!$app->isClient('site')) {
            return;
        }

        $features = RoutingFeatures::fromRegistry($this->params);

        if (!$features->hierarchicalTags && !$features->facetedCategories) {
            return;
        }

        (new BreadcrumbManager($app, $features, $this->tags(), $this->taxonomies()))->apply();
    }

    private function tags(): TagHierarchyRepository
    {
        return $this->tagRepository ??= new TagHierarchyRepository(
            Factory::getContainer()->get(DatabaseInterface::class),
            new TagHierarchyResolver(),
        );
    }

    private function taxonomies(): CategoryTaxonomyResolver
    {
        return $this->taxonomyRepository ??= new CategoryTaxonomyResolver(
            Factory::getContainer()->get(DatabaseInterface::class),
        );
    }
}
