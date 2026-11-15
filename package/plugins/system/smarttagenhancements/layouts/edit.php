<?php
defined('_JEXEC') or die;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

$form = $displayData['form'];
$tabs = $displayData['tabs'];
$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$options = ['active' => $displayData['activeTab'], 'breakpoint' => 768];
if (!$displayData['validationFailed']) { $options['recall'] = true; }
?>
<div class="smarttag-editor">
    <h1><?= Text::_('PLG_SYSTEM_SMARTTAGENHANCEMENTS_EDIT') ?></h1>
    <form action="<?= $escape($displayData['action']) ?>" method="post" name="adminForm" id="adminForm" class="form-validate" novalidate data-invalid-field="<?= $escape($displayData['invalidField']) ?>">
        <?= HTMLHelper::_('uitab.startTabSet', 'smarttag-editor-tabs', $options) ?>
        <?php foreach ($tabs as $id => $tab) : ?>
            <?= HTMLHelper::_('uitab.addTab', 'smarttag-editor-tabs', $id, $tab['label']) ?>
            <?php foreach ($tab['fields'] as $field) : ?>
                <?= $field->renderField() ?>
            <?php endforeach ?>
            <?= HTMLHelper::_('uitab.endTab') ?>
        <?php endforeach ?>
        <?= HTMLHelper::_('uitab.endTabSet') ?>
        <?php foreach ($displayData['hiddenFields'] as $field) : ?>
            <?= $field->input ?>
        <?php endforeach ?>
        <div class="d-flex flex-wrap gap-2 my-3">
            <button type="submit" name="task" value="tag.save" class="btn btn-primary"><?= Text::_('PLG_SYSTEM_SMARTTAGENHANCEMENTS_SAVE_CLOSE') ?></button>
            <button type="submit" name="task" value="tag.apply" class="btn btn-secondary"><?= Text::_('PLG_SYSTEM_SMARTTAGENHANCEMENTS_APPLY') ?></button>
            <button type="submit" name="task" value="tag.cancel" class="btn btn-secondary" formnovalidate><?= Text::_('PLG_SYSTEM_SMARTTAGENHANCEMENTS_CANCEL') ?></button>
        </div>
        <input type="hidden" name="jform[id]" value="<?= (int) $displayData['item']->id ?>">
        <input type="hidden" name="return" value="<?= $escape($displayData['return']) ?>">
        <?= HTMLHelper::_('form.token') ?>
    </form>
</div>
