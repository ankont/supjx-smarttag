<?php
declare(strict_types=1);
namespace SuperSoft\Plugin\System\SmartTagEnhancements;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Multilanguage;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use Joomla\Database\DatabaseInterface;

/** Read tag-owned fields, never the fields of tagged articles/categories. */
final class TagFields
{
    public const CONTEXT = 'com_tags.tag';

    public static function get(int|object $tag, bool $prepareValue = true): array
    {
        if (!Configuration\EnhancementOptions::customFields()) { return []; }
        $id = is_int($tag) ? $tag : (int) ($tag->id ?? 0);
        if ($id < 2 || (is_object($tag) && !empty($tag->type_alias) && $tag->type_alias !== self::CONTEXT)) { return []; }
        $app = Factory::getApplication();
        $user = $app->getIdentity();
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->createQuery()->select('id,title,alias,parent_id,published,access,language')
            ->from('#__tags')->where('id = ' . $id);
        $record = $db->setQuery($query)->loadObject();
        if (!$record) { return []; }
        $admin = $app->isClient('administrator') && $user->authorise('core.manage', 'com_tags');
        if (!$admin && ((int) $record->published !== 1
            || !in_array((int) $record->access, array_map('intval', $user->getAuthorisedViewLevels()), true)
            || (Multilanguage::isEnabled() && !in_array($record->language, ['*', $app->getLanguage()->getTag()], true)))) { return []; }
        if (Multilanguage::isEnabled() && $record->language === '*') { $record->language = $app->getLanguage()->getTag(); }
        return array_values(FieldsHelper::getFields(self::CONTEXT, $record, $prepareValue));
    }

    public static function byName(int|object $tag, bool $prepareValue = true): array
    {
        $result = [];
        foreach (self::get($tag, $prepareValue) as $field) { $result[$field->name] = $field; }
        return $result;
    }

    public static function attach(object $tag, bool $prepareValue = true): void
    {
        if (!empty($tag->type_alias) && $tag->type_alias !== self::CONTEXT) { return; }
        $tag->jcfields = [];
        foreach (self::get($tag, $prepareValue) as $field) { $tag->jcfields[$field->id] = $field; }
    }

    /** Explicit manual display through Joomla's normal fields layouts/overrides. */
    public static function render(int|object $tag): string
    {
        $fields = self::get($tag, true);
        if ($fields === []) { return ''; }
        $item = (object) ['id'=>is_int($tag) ? $tag : (int) ($tag->id ?? 0)];
        return FieldsHelper::render(self::CONTEXT, 'fields.render', ['item'=>$item,'context'=>self::CONTEXT,'fields'=>$fields]);
    }
}
