<?php
declare(strict_types=1);
namespace SuperSoft\Plugin\System\SmartTagEnhancements\Administrator\Model;

use Joomla\CMS\Factory;
use SuperSoft\Plugin\System\SmartTagEnhancements\CopyValues;

final class TagModel extends \Joomla\Component\Tags\Administrator\Model\TagModel
{
    protected $option = 'com_tags';

    public function save($data)
    {
        $input = Factory::getApplication()->getInput();
        $source = $input->getCmd('task') === 'save2copy' ? $input->getInt('id') : 0;
        $result = parent::save($data);
        if ($result && $source > 1) {
            CopyValues::copy($source, (int) $this->getState($this->getName() . '.id'), array_keys((array) ($data['com_fields'] ?? [])));
        }
        return $result;
    }

    protected function batchCopy($value, $pks, $contexts)
    {
        $result = parent::batchCopy($value, $pks, $contexts);
        if (is_array($result)) {
            foreach ($result as $source => $target) { CopyValues::copy((int) $source, (int) $target); }
        }
        return $result;
    }
}
