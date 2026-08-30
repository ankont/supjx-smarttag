<?php

declare(strict_types=1);

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\SiteRouter;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use SuperSoft\Plugin\System\TagRouting\Extension\TagRouting;

defined('_JEXEC') or die;

return new class implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(PluginInterface::class, static function (Container $container): PluginInterface {
            $plugin = new TagRouting(
                $container->get(DispatcherInterface::class),
                (array) PluginHelper::getPlugin('system', 'tagrouting'),
            );
            $plugin->setApplication(Factory::getApplication());
            if ($container->has(SiteRouter::class)) {
                $plugin->setSiteRouter($container->get(SiteRouter::class));
            }

            return $plugin;
        });
    }
};
