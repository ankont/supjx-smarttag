<?php
declare(strict_types=1);
namespace SuperSoft\Plugin\System\SmartTagEnhancements\Configuration;

use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;

final class EnhancementOptions
{
    public const DEFAULTS = ['enable_frontend_editing' => 1, 'enable_custom_fields' => 1];

    public static function get(): Registry
    {
        $params = new Registry(PluginHelper::getPlugin('system', 'smarttagenhancements')->params ?? '');
        return new Registry(array_replace(self::DEFAULTS, $params->toArray()));
    }

    public static function frontendEditing(): bool
    {
        return (int) self::get()->get('enable_frontend_editing') === 1;
    }

    public static function customFields(): bool
    {
        return (int) self::get()->get('enable_custom_fields') === 1;
    }
}
