<?php
declare(strict_types=1);
namespace SuperSoft\Plugin\System\SmartTagEnhancements\Administrator\Model;

use Joomla\CMS\Factory;
use SuperSoft\Plugin\System\SmartTagEnhancements\TagFields;

/** Scope tag list preferences while keeping native field queries and ACL. */
final class GroupsModel extends \Joomla\Component\Fields\Administrator\Model\GroupsModel
{
    protected $option = 'com_fields';
    protected $filterFormName = 'filter_groups';

    protected function populateState($ordering = null, $direction = null)
    {
        $app = Factory::getApplication();
        $context = $app->getUserStateFromRequest('com_fields.groups.context', 'context', 'com_content', 'CMD');
        if ($app->isClient('administrator') && $context === TagFields::CONTEXT) {
            $this->context = 'com_fields.groups.com_tags.tag';
        }
        parent::populateState($ordering, $direction);
    }
}
