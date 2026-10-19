<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Service;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Form\FormFactoryInterface;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Router\SiteRouter;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use SuperSoft\Plugin\System\TagRouting\MVC\FacetedMVCFactory;

final readonly class FacetedMVCFactoryProvider implements ServiceProviderInterface
{
    public function __construct(private string $namespace, private string $coreNamespace = 'Joomla\\Component\\Content')
    {
    }

    public function register(Container $container): void
    {
        $container->set(MVCFactoryInterface::class, function (Container $container): MVCFactoryInterface {
            $factory = new FacetedMVCFactory($this->namespace, null, $this->coreNamespace);
            $factory->setFormFactory($container->get(FormFactoryInterface::class));
            $factory->setDispatcher($container->get(DispatcherInterface::class));
            $factory->setDatabase($container->get(DatabaseInterface::class));
            $factory->setSiteRouter($container->get(SiteRouter::class));
            $factory->setCacheControllerFactory($container->get(CacheControllerFactoryInterface::class));
            $factory->setUserFactory($container->get(UserFactoryInterface::class));
            $factory->setMailerFactory($container->get(MailerFactoryInterface::class));

            return $factory;
        });
    }
}
