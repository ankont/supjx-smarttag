<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Tags\Site\Model;

use Joomla\CMS\Factory;
use SuperSoft\Plugin\System\TagRouting\Tags\PublicationQueryGuard;

/** Revalidates native content against its live record, not only the UCM copy. */
final class TagModel extends \Joomla\Component\Tags\Site\Model\TagModel
{
    protected function getListQuery()
    {
        $query = parent::getListQuery();
        $db = $this->getDatabase();
        $query->where(PublicationQueryGuard::condition(
            $db->quote('com_content.article'),
            $db->quote('com_content.category'),
            $db->quote(Factory::getDate()->toSql()),
            $db->quote($db->getNullDate()),
            array_map('intval', $this->getCurrentUser()->getAuthorisedViewLevels()),
        ));

        return $query;
    }
}
