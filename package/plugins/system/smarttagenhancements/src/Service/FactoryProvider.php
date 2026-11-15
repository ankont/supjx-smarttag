<?php
declare(strict_types=1);
namespace SuperSoft\Plugin\System\SmartTagEnhancements\Service;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Form\FormFactoryInterface;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use SuperSoft\Plugin\System\SmartTagEnhancements\MVC\TagFieldsFactory;
use SuperSoft\Plugin\System\SmartTagEnhancements\MVC\FieldsListFactory;

final class FactoryProvider implements ServiceProviderInterface
{
    public function __construct(private string $component = 'tags')
    {
    }

    public function register(Container $container): void
    {
        $component = $this->component;
        $container->set(MVCFactoryInterface::class, static function (Container $container) use ($component): MVCFactoryInterface {
            $factory = $component === 'fields'
                ? new FieldsListFactory('Joomla\\Component\\Fields')
                : new TagFieldsFactory('SuperSoft\\Plugin\\System\\SmartTagEnhancements');
            $factory->setFormFactory($container->get(FormFactoryInterface::class));
            $factory->setDispatcher($container->get(DispatcherInterface::class));
            $factory->setDatabase($container->get(DatabaseInterface::class));
            $factory->setCacheControllerFactory($container->get(CacheControllerFactoryInterface::class));
            $factory->setUserFactory($container->get(UserFactoryInterface::class));
            $factory->setMailerFactory($container->get(MailerFactoryInterface::class));
            return $factory;
        });
    }
}
