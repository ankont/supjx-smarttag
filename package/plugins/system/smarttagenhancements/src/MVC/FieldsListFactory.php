<?php
declare(strict_types=1);
namespace SuperSoft\Plugin\System\SmartTagEnhancements\MVC;

/** Keep native controllers, views and edit models for com_fields. */
final class FieldsListFactory extends \Joomla\CMS\MVC\Factory\MVCFactory
{
    protected function getClassName(string $suffix, string $prefix)
    {
        if (strtolower($prefix) === 'administrator'
            && in_array($suffix, ['Model\\FieldsModel', 'Model\\GroupsModel'], true)) {
            return 'SuperSoft\\Plugin\\System\\SmartTagEnhancements\\Administrator\\' . $suffix;
        }
        return parent::getClassName($suffix, $prefix);
    }
}
