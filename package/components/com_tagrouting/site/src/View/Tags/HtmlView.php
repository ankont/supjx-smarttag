<?php

declare(strict_types=1);

namespace SuperSoft\Component\TagRouting\Site\View\Tags;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Exception\RouteNotFoundException;

final class HtmlView extends BaseHtmlView
{
    public function display($tpl = null): void
    {
        // Valid child paths are rewritten by the system plugin to com_tags
        // before dispatch. The context component never renders tag content.
        throw new RouteNotFoundException(Text::_('COM_TAGROUTING_NO_TAG'));
    }
}
