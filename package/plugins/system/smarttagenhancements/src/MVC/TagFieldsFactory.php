<?php
declare(strict_types=1);
namespace SuperSoft\Plugin\System\SmartTagEnhancements\MVC;

/** Administrator only. Site models and routing factories are never replaced. */
final class TagFieldsFactory extends \Joomla\CMS\MVC\Factory\MVCFactory
{
    protected function getClassName(string $suffix, string $prefix)
    {
        $overlay = 'SuperSoft\\Plugin\\System\\SmartTagEnhancements\\' . ucfirst($prefix) . '\\' . $suffix;
        if (class_exists($overlay)) { return $overlay; }
        $native = 'Joomla\\Component\\Tags\\' . ucfirst($prefix) . '\\' . $suffix;
        return class_exists($native) ? $native : null;
    }
}
