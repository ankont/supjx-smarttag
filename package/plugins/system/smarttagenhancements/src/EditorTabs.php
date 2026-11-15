<?php
declare(strict_types=1);
namespace SuperSoft\Plugin\System\SmartTagEnhancements;

use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;

/** Present native fields once, keeping their groups and validation rules. */
final class EditorTabs
{
    public static function prepare(Form $form): array
    {
        $tabs = [];
        $hidden = [];
        $seen = [];
        $sections = [
            'smarttag-details' => ['label' => Text::_('PLG_SYSTEM_SMARTTAGENHANCEMENTS_TAB_DETAILS'), 'fields' => []],
            'smarttag-publishing' => ['label' => Text::_('PLG_SYSTEM_SMARTTAGENHANCEMENTS_TAB_PUBLISHING'), 'fields' => []],
        ];
        foreach (['smarttag-details' => ['title', 'alias', 'parent_id', 'description'],
            'smarttag-publishing' => ['published', 'access', 'language', 'note']] as $id => $names) {
            foreach ($names as $name) {
                if ($field = $form->getField($name)) { $sections[$id]['fields'][] = $field; }
            }
        }
        foreach ($form->getFieldsets('com_fields') as $fieldset) {
            $sections['smarttag-custom-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $fieldset->name)] = [
                'label' => Text::_($fieldset->label),
                'fields' => $form->getFieldset($fieldset->name),
            ];
        }
        foreach ($sections as $id => $section) {
            $fields = [];
            foreach ($section['fields'] as $field) {
                if (isset($seen[$field->name])) { continue; }
                $seen[$field->name] = true;
                if ($field->hidden) { $hidden[] = $field; }
                else { $fields[] = $field; }
            }
            if ($fields !== []) { $tabs[$id] = ['label' => $section['label'], 'fields' => $fields]; }
        }
        return ['tabs' => $tabs, 'hidden' => $hidden];
    }

    /** Joomla errors have no field ID. Locate the first failure with native field validation. */
    public static function firstInvalid(Form $form, array $tabs, array $data): ?array
    {
        $input = new Registry($form->filter($data));
        foreach ($tabs as $id => $tab) {
            foreach ($tab['fields'] as $field) {
                if ($field->disabled) { continue; }
                $key = $field->group ? $field->group . '.' . $field->fieldname : $field->fieldname;
                try { $valid = $field->validate($input->get($key), $field->group, $input); }
                catch (\Throwable $error) { $valid = $error; }
                if ($valid === false || $valid instanceof \Throwable) {
                    return ['tab' => $id, 'field' => $field->id];
                }
            }
        }
        return null;
    }
}
