<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Extension;

use Joomla\CMS\Event\Application\AfterInitialiseEvent;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\RouterFactory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\SiteRouter;
use Joomla\CMS\Router\SiteRouterAwareInterface;
use Joomla\CMS\Router\SiteRouterAwareTrait;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use SuperSoft\Plugin\System\TagRouting\Domain\SerializedExpression;
use SuperSoft\Plugin\System\TagRouting\Menu\LogicalFilterState;
use SuperSoft\Plugin\System\TagRouting\Menu\MenuTagContextResolver;
use SuperSoft\Plugin\System\TagRouting\Routing\GenericMenuBuildRule;
use SuperSoft\Plugin\System\TagRouting\Service\FacetedMVCFactoryProvider;

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

        [, $type, $extensionName, $container] = array_values($event->getArguments());

        if ($type !== ComponentInterface::class || $extensionName !== 'content') {
            return;
        }

        $namespace = '\\SuperSoft\\Plugin\\System\\TagRouting';
        $container->registerServiceProvider(new RouterFactory($namespace));
        $container->registerServiceProvider(new FacetedMVCFactoryProvider($namespace));
    }

    public function onAfterInitialise(AfterInitialiseEvent $event): void
    {
        $app = $event->getApplication();

        if (!$app->isClient('site')) {
            return;
        }

        $contexts = new MenuTagContextResolver(
            $app->getMenu(),
            new LogicalFilterState(new SerializedExpression()),
        );

        // PROCESS_BEFORE still runs before SEF assembly. This rule also covers
        // com_tags, whose component router does not need to be replaced.
        $this->getSiteRouter()->attachBuildRule(
            new GenericMenuBuildRule($contexts),
            SiteRouter::PROCESS_BEFORE,
        );
    }
}
