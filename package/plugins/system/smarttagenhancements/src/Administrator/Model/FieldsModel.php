<?php
declare(strict_types=1);
namespace SuperSoft\Plugin\System\SmartTagEnhancements\Administrator\Model;

use Joomla\CMS\Factory;
use SuperSoft\Plugin\System\SmartTagEnhancements\TagFields;

/** Scope tag list preferences while keeping native field queries and ACL. */
final class FieldsModel extends \Joomla\Component\Fields\Administrator\Model\FieldsModel
{
    protected $option = 'com_fields';
    protected $filterFormName = 'filter_fields';

    protected function getStoreId($id = '')
    {
        return parent::getStoreId($id . ':smarttag-fields:' . (int) \SuperSoft\Plugin\System\SmartTagEnhancements\Configuration\EnhancementOptions::customFields());
    }

    protected function getListQuery()
    {
        $query = parent::getListQuery();
        if ($this->getState('filter.context') === TagFields::CONTEXT
            && !\SuperSoft\Plugin\System\SmartTagEnhancements\Configuration\EnhancementOptions::customFields()) {
            $query->where('1 = 0');
        }
        return $query;
    }

    protected function populateState($ordering = null, $direction = null)
    {
        $app = Factory::getApplication();
        $context = $app->getUserStateFromRequest('com_fields.fields.context', 'context', 'com_content.article', 'CMD');
        if ($app->isClient('administrator') && $context === TagFields::CONTEXT) {
            $this->context = 'com_fields.fields.com_tags.tag';
        }
        parent::populateState($ordering, $direction);
    }
}
