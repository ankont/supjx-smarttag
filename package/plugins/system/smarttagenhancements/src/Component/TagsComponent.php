<?php
declare(strict_types=1);
namespace SuperSoft\Plugin\System\SmartTagEnhancements\Component;

use Joomla\CMS\Fields\FieldsServiceInterface;
use Joomla\CMS\Language\Text;
use SuperSoft\Plugin\System\SmartTagEnhancements\TagFields;

/** Native Tags behavior, with the additional Joomla Fields context. */
final class TagsComponent extends \Joomla\Component\Tags\Administrator\Extension\TagsComponent implements FieldsServiceInterface
{
    public function validateSection($section, $item = null)
    {
        return $section === 'tag' ? 'tag' : null;
    }

    public function getContexts(): array
    {
        return [TagFields::CONTEXT => Text::_('PLG_SYSTEM_SMARTTAGENHANCEMENTS_CONTEXT')];
    }
}
