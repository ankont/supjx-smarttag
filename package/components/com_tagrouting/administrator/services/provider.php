<?php

declare(strict_types=1);

use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Component\Router\RouterFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\Extension\Service\Provider\RouterFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use SuperSoft\Component\TagRouting\Administrator\Extension\TagRoutingComponent;

defined('_JEXEC') or die;

return new class implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->registerServiceProvider(new MVCFactory('\\SuperSoft\\Component\\TagRouting'));
        $container->registerServiceProvider(new ComponentDispatcherFactory('\\SuperSoft\\Component\\TagRouting'));
        $container->registerServiceProvider(new RouterFactory('\\SuperSoft\\Component\\TagRouting'));
        $container->set(ComponentInterface::class, static function (Container $container): ComponentInterface {
            $component = new TagRoutingComponent($container->get(ComponentDispatcherFactoryInterface::class));
            $component->setMVCFactory($container->get(MVCFactoryInterface::class));
            $component->setRouterFactory($container->get(RouterFactoryInterface::class));

            return $component;
        });
    }
};
