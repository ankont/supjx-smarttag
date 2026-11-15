<?php
declare(strict_types=1);
namespace SuperSoft\Plugin\System\SmartTagEnhancements;

use Joomla\CMS\Factory;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;

final class CopyValues
{
    /** Native raw values preserve multi-values/JSON without knowing the field type. */
    public static function copy(int $source, int $target, array $submittedNames = []): void
    {
        if ($source < 2 || $target < 2 || $source === $target) { return; }
        $model = Factory::getApplication()->bootComponent('com_fields')->getMVCFactory()
            ->createModel('Field', 'Administrator', ['ignore_request'=>true]);
        foreach (TagFields::get($source, false) as $field) {
            if (in_array($field->name, $submittedNames, true) || !FieldsHelper::canEditFieldValue($field)) { continue; }
            $value = $model->getFieldValue($field->id, $source);
            if ($value !== null) {
                // Native setFieldValue also enforces core.edit.value; no ACL bypass.
                if (!$model->setFieldValue($field->id, $target, $value)) {
                    throw new \RuntimeException('Unable to copy custom field values to the new tag.');
                }
            }
        }
    }
}
