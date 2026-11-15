<?php
declare(strict_types=1);
namespace SuperSoft\Plugin\System\SmartTagEnhancements\MVC;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Input\Input;
use SuperSoft\Plugin\System\SmartTagEnhancements\Site\Controller\TagController;

/** Delegate the existing tag views, models, tables and other controllers. */
final class EditorFactory implements MVCFactoryInterface
{
    public function __construct(private MVCFactoryInterface $native)
    {
    }

    public function createController($name, $prefix, array $config, CMSApplicationInterface $app, Input $input)
    {
        if (strtolower($name) === 'tag' && strtolower($prefix) === 'site') {
            return new TagController($config, $this, $app, $input);
        }
        return $this->native->createController($name, $prefix, $config, $app, $input);
    }

    public function createModel($name, $prefix = '', array $config = [])
    {
        return $this->native->createModel($name, $prefix, $config);
    }

    public function createView($name, $prefix = '', $type = '', array $config = [])
    {
        return $this->native->createView($name, $prefix, $type, $config);
    }

    public function createTable($name, $prefix = '', array $config = [])
    {
        return $this->native->createTable($name, $prefix, $config);
    }
}
