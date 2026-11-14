<?php
declare(strict_types=1);
namespace SuperSoft\Plugin\System\TagRouting\Field;

use Joomla\CMS\Form\Field\NoteField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;

/** Short, always-visible introduction linking to the plugin's native help tab. */
final class HelpSummaryField extends NoteField
{
    protected function getLabel()
    {
        $id = (int) (PluginHelper::getPlugin('system', 'tagrouting')->id ?? 0);
        $url = Route::_('index.php?option=com_plugins&view=plugin&layout=edit&extension_id=' . $id, false) . '#attrib-howto';
        $this->element['description'] = Text::sprintf('PLG_SYSTEM_TAGROUTING_HELP_SUMMARY',
            htmlspecialchars($url, ENT_QUOTES, 'UTF-8'));
        return parent::getLabel();
    }
}
