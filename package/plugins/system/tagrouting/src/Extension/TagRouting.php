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
use SuperSoft\Plugin\System\TagRouting\Menu\LogicalFilterState;
use SuperSoft\Plugin\System\TagRouting\Menu\MenuTagContextResolver;
use SuperSoft\Plugin\System\TagRouting\Routing\GenericMenuBuildRule;
use SuperSoft\Plugin\System\TagRouting\Routing\InternalQueryBoundary;
use SuperSoft\Plugin\System\TagRouting\Routing\FacetedRequest;
use SuperSoft\Plugin\System\TagRouting\Routing\TagPageState;
use SuperSoft\Plugin\System\TagRouting\Infrastructure\CategoryTaxonomyResolver;
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
    private ?\SuperSoft\Plugin\System\TagRouting\Configuration\RoutingOptionsSave $optionsSave = null;

    public static function getSubscribedEvents(): array
    {
        return [
            'onExtensionBeforeSave' => 'onExtensionBeforeSave',
            'onExtensionAfterSave' => 'onExtensionAfterSave',
            'onContentPrepareData' => 'onContentPrepareData',
            'onContentPrepareForm' => 'onContentPrepareForm',
            'onAfterExtensionBoot' => 'onAfterExtensionBoot',
            'onAfterInitialise' => 'onAfterInitialise',
            'onAfterRoute' => 'onAfterRoute',
            'onAfterDispatch' => 'onAfterDispatch',
        ];
    }

    public function onContentPrepareData(Event $event): void
    {
        if ($event->getArgument('context') !== 'com_plugins.plugin') { return; }
        $data = $event->getArgument('data');
        if (is_object($data) && ($data->element ?? '') === 'tagrouting' && ($data->folder ?? '') === 'system') {
            $params = new \Joomla\Registry\Registry($data->params ?? []);
            $values = \SuperSoft\Plugin\System\TagRouting\Configuration\RoutingOptions::merge($params->toArray());
            foreach ($values as $key => $value) { $params->set($key, $value); }
            $data->params = $params->toArray();
        }
    }

    public function onExtensionBeforeSave(Event $event): void
    {
        if (!$this->getApplication()->isClient('administrator')) { return; }
        $subject = $event->getArgument('subject');
        $context = (string) $event->getArgument('context');
        if (!is_object($subject)) { return; }
        if (($subject->type ?? '') === 'plugin' && ($subject->element ?? '') === 'tagrouting'
            && ($subject->folder ?? '') === 'system') {
            $params = new \Joomla\Registry\Registry(\SuperSoft\Plugin\System\TagRouting\Configuration\RoutingOptions::normalize((new \Joomla\Registry\Registry($subject->params ?? ''))->toArray() + ['custom_marker'=>\SuperSoft\Plugin\System\TagRouting\Configuration\RoutingOptions::get()->get('custom_marker', '')]));
            $params->set('enable_custom_marker', in_array('__custom__', (array) $params->get('accepted_markers', []), true) ? 1 : 0);
            $subject->params = $params->toString();
            return;
        }
        if ($context !== 'com_config.component' || ($subject->element ?? '') !== 'com_tags') { return; }
        $data = (new \Joomla\Registry\Registry($subject->params ?? ''))->toArray();
        $hasRouting = array_intersect_key($data, array_fill_keys(array_map(static fn ($key) => 'smarttag_' . $key,
            array_keys(\SuperSoft\Plugin\System\TagRouting\Configuration\RoutingOptions::DEFAULTS)), true)) !== [];
        if (!$hasRouting) { return; }
        if (!$this->getApplication()->getIdentity()->authorise('core.edit', 'com_plugins')) {
            throw new \RuntimeException('Not authorised to edit SmartTag Routing settings.', 403);
        }
        $this->optionsSave ??= new \SuperSoft\Plugin\System\TagRouting\Configuration\RoutingOptionsSave(
            \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class));
        $this->optionsSave->before($context, $subject);
    }

    public function onExtensionAfterSave(Event $event): void
    {
        $subject = $event->getArgument('subject');
        if ($this->optionsSave !== null && is_object($subject)) {
            $this->optionsSave->after((string) $event->getArgument('context'), $subject);
        }
    }

    public function onContentPrepareForm(Event $event): void
    {
        if (!$this->getApplication()->isClient('administrator')) { return; }
        $form = null;
        foreach ($event->getArguments() as $argument) {
            if ($argument instanceof \Joomla\CMS\Form\Form) { $form = $argument; break; }
        }
        if ($form === null) { return; }
        $this->loadLanguage();
        if ($form->getName() === 'com_config.component'
            && $this->getApplication()->getInput()->getCmd('component') === 'com_tags') {
            if (!$this->getApplication()->getIdentity()->authorise('core.edit', 'com_plugins')) { return; }
            $form->loadFile(dirname(__DIR__, 2) . '/forms/routing.xml');
            foreach (\SuperSoft\Plugin\System\TagRouting\Configuration\RoutingOptions::get()->toArray() as $key => $value) {
                if (array_key_exists($key, \SuperSoft\Plugin\System\TagRouting\Configuration\RoutingOptions::DEFAULTS)) {
                    // Core binds native Tags data afterwards; SmartTag values come only from its plugin record.
                    $form->setValue('smarttag_' . $key, null, $value);
                }
            }
        }
        if ($form->getName() === 'com_menus.item') {
            $options = $form->getXml()->xpath('//field[@name="tag_facet_descendants"]/option[@value=""]');
            foreach ($options ?: [] as $option) {
                $default = \SuperSoft\Plugin\System\TagRouting\Configuration\RoutingOptions::descendants('');
                $option[0] = \Joomla\CMS\Language\Text::sprintf('JGLOBAL_USE_GLOBAL_VALUE',
                    \Joomla\CMS\Language\Text::_($default ? 'JYES' : 'JNO'));
            }
        }
    }

    public function onAfterExtensionBoot(Event $event): void
    {
        if (!$this->getApplication()->isClient('site')) {
            return;
        }

        $features = RoutingFeatures::fromPlugin();

        [, $type, $extensionName, $container] = array_values($event->getArguments());

        if ($type === ComponentInterface::class && $extensionName === 'tags' && $features->hierarchicalTags) {
            $container->registerServiceProvider(new RouterFactory('\\SuperSoft\\Plugin\\System\\TagRouting\\Tags'));
            $container->registerServiceProvider(new FacetedMVCFactoryProvider(
                '\\SuperSoft\\Plugin\\System\\TagRouting\\Tags',
                'Joomla\\Component\\Tags',
            ));

            return;
        }

        if (!$features->needsContentRouter()) {
            return;
        }

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

        $features = RoutingFeatures::fromPlugin();

        $router = $this->getSiteRouter();
        FacetedRequest::reset($app);
        \SuperSoft\Plugin\System\TagRouting\Routing\TagFilterRequest::reset($app);
        $app->getInput()->set(\SuperSoft\Plugin\System\TagRouting\Routing\TagFilterRequest::KEY, null);
        TagPageState::reset($app->getMenu());
        CategoryTaxonomyResolver::resetForApplication($app);
        $app->getInput()->set(SerializedExpression::REQUEST_KEY, null);
        $app->getInput()->set(TagPageState::REQUEST_KEY, null);
        $app->getInput()->set(TagPageState::SCOPE_KEY, null);
        $boundary = new InternalQueryBoundary($app);
        $router->attachParseRule($boundary->parse(...), SiteRouter::PROCESS_BEFORE);
        $router->attachBuildRule($boundary->build(...), SiteRouter::PROCESS_AFTER);

        if ($features->hierarchicalTags) {
            $repository = $this->tags();
            $menus = new StandaloneMenuContextResolver($app->getMenu(), new StandaloneMenuSelector());
            $markers = new FacetDelimiter($features->acceptedMarkers, $features->canonicalMarker);

            // Capture the tag ID before cleanup or the core com_tags router can
            // consume it. This rule only marks single-tag page builds.
            $router->attachBuildRule(new StandaloneTagPrepareBuildRule($app), SiteRouter::PROCESS_BEFORE);
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

        $features = RoutingFeatures::fromPlugin();

        if (!$features->hierarchicalTags) {
            return;
        }

        (new BreadcrumbManager($app, $features, $this->tags()))->apply();
    }

    public function onAfterRoute(Event $event): void
    {
        $app = $this->getApplication();

        if (!$app->isClient('site')) {
            return;
        }

        $input = $app->getInput();

        if ($input->getCmd('option') !== 'com_tagrouting' || $input->getCmd('view', 'tags') !== 'tags') {
            return;
        }

        $active = $app->getMenu()->getActive();

        if ($active === null) {
            return;
        }

        $landingPage = (string) $active->getParams()->get('landing_page', 'virtual_root');

        if (!in_array($landingPage, ['top_level_tags', 'all_tags'], true)) {
            return;
        }

        // Keep the SupJx SmartTag menu item active for Itemid, modules, template, and
        // page context while dispatching Joomla's native all-tags view. Core
        // TagsModel treats parent_id=1 as the direct children of its root tag;
        // parent_id=0 leaves the hierarchy unrestricted.
        $input->set('option', 'com_tags');
        $input->set('view', 'tags');
        $input->set('parent_id', $landingPage === 'top_level_tags' ? 1 : 0);
    }

    private function tags(): TagHierarchyRepository
    {
        return $this->tagRepository ??= new TagHierarchyRepository(
            Factory::getContainer()->get(DatabaseInterface::class),
            new TagHierarchyResolver(),
        );
    }
}
