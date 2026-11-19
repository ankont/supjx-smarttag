<?php
declare(strict_types=1);
namespace SuperSoft\Plugin\System\TagRouting\Field;

use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use SuperSoft\Plugin\System\TagRouting\Configuration\RoutingOptions;

/** Compact marker controls; one array-valued field with native Joomla radio switchers. */
final class MarkerControlsField extends FormField
{
    protected $type = 'MarkerControls';

    protected function getInput()
    {
        $escape = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $name = preg_replace('/\[\]$/', '', $this->name);
        $prefix = str_starts_with($this->fieldname, 'smarttag_') ? 'smarttag_' : '';
        $values = RoutingOptions::normalize([
            'accepted_markers'=>(array) $this->value,
            'custom_marker'=>$this->form->getValue($prefix . 'custom_marker', $this->group, ''),
        ]);
        $accepted = (array) ($values['accepted_markers'] ?? []);
        $html = '<div class="d-flex flex-wrap align-items-center gap-2 text-nowrap" id="' . $escape($this->id) . '" role="group" aria-labelledby="' . $escape($this->id . '-lbl') . '">';
        foreach (['short'=>'!', 'tag'=>'!tag', 'filter'=>'!filter', 'custom_enabled'=>Text::_('PLG_SYSTEM_TAGROUTING_CUSTOM_TOGGLE')] as $key => $marker) {
            $acceptedValue = $key === 'custom_enabled' ? '__custom__' : $marker;
            $options = [];
            foreach ([0=>'JNO', 1=>'JYES'] as $value => $text) {
                $options[] = (object) ['value'=>(string) $value,
                    'text'=>$escape($marker) . '<span class="visually-hidden"> - ' . $escape(Text::_($text)) . '</span>'];
            }
            $html .= LayoutHelper::render('joomla.form.field.radio.switcher', [
                'id'=>$this->id . '_' . $key, 'name'=>$name . '[' . $key . ']',
                'label'=>$escape($marker), 'value'=>in_array($acceptedValue, $accepted, true) ? '1' : '0',
                'options'=>$options, 'disabled'=>(bool) $this->disabled, 'readonly'=>(bool) $this->readonly,
                'class'=>'flex-shrink-0', 'onchange'=>'', 'dataAttribute'=>'',
            ]);
        }
        return $html . '</div>';
    }
}
