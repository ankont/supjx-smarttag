<?php
declare(strict_types=1);
namespace SuperSoft\Plugin\System\SmartTagEnhancements\Configuration;

use Joomla\CMS\Form\Form;

final class OptionsForm
{
    public static function add(Form $form): void
    {
        $manifest = simplexml_load_file(dirname(__DIR__, 2) . '/smarttagenhancements.xml');
        $fieldset = $manifest->config->fields->fieldset;
        $fieldset['name'] = 'smarttag_enhancements';
        $fieldset['label'] = 'PLG_SYSTEM_SMARTTAGENHANCEMENTS_OPTIONS_TAB';
        foreach ($fieldset->field as $field) {
            $field['name'] = 'enhancements_' . (string) $field['name'];
        }
        $form->load('<form>' . $fieldset->asXML() . '</form>');
        foreach (EnhancementOptions::DEFAULTS as $key => $default) {
            $form->setValue('enhancements_' . $key, null, EnhancementOptions::get()->get($key, $default));
        }
    }
}
