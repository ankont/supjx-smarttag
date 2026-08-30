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
use SuperSoft\Plugin\System\TagRouting\Domain\SerializedExpression;
use SuperSoft\Plugin\System\TagRouting\Menu\LogicalFilterState;
use SuperSoft\Plugin\System\TagRouting\Menu\MenuTagContextResolver;
use SuperSoft\Plugin\System\TagRouting\Routing\GenericMenuBuildRule;
use SuperSoft\Plugin\System\TagRouting\Routing\HierarchicalTagBuildRule;
use SuperSoft\Plugin\System\TagRouting\Routing\HierarchicalTagParseRule;
use SuperSoft\Plugin\System\TagRouting\Routing\HierarchicalTagPrepareBuildRule;
use SuperSoft\Plugin\System\TagRouting\Service\FacetedMVCFactoryProvider;
use SuperSoft\Plugin\System\TagRouting\Tag\TagHierarchyRepository;
use SuperSoft\Plugin\System\TagRouting\Tag\TagHierarchyResolver;
use SuperSoft\Plugin\System\TagRouting\Tag\TagMenuContextResolver;

final class TagRouting extends CMSPlugin implements SubscriberInterface, SiteRouterAwareInterface
{
    use SiteRouterAwareTrait;

    public static function getSubscribedEvents(): array
    {
        return [
            'onAfterExtensionBoot' => 'onAfterExtensionBoot',
            'onAfterInitialise' => 'onAfterInitialise',
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
            $repository = new TagHierarchyRepository(
                Factory::getContainer()->get(DatabaseInterface::class),
                new TagHierarchyResolver(),
            );

            // Capture the tag ID before cleanup or the core com_tags router can
            // consume it. This rule only marks single-tag page builds.
            $router->attachBuildRule(new HierarchicalTagPrepareBuildRule(), SiteRouter::PROCESS_BEFORE);
            $router->attachParseRule(
                new HierarchicalTagParseRule(
                    $app,
                    $repository,
                    new TagMenuContextResolver($app->getMenu()),
                    $features->tagPrefix,
                ),
                SiteRouter::PROCESS_BEFORE,
            );
            // The component route has already been built at PROCESS_DURING;
            // this rule replaces only explicitly marked tag-page routes.
            $router->attachBuildRule(
                new HierarchicalTagBuildRule($app, $repository, $features->tagPrefix),
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
}
