<?php

declare(strict_types=1);

namespace SuperSoft\Component\TagRouting\Site\Model;

use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Pagination\Pagination;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

/** @internal Supplies an empty data set to Joomla's native com_tags Tag view. */
final class VirtualTagModel extends BaseDatabaseModel
{
    private Pagination $pagination;

    public function __construct(Registry $params, DatabaseInterface $database)
    {
        parent::__construct(['dbo' => $database]);

        $this->setState('params', $params);
        $this->setState('list.filter', '');
        $this->pagination = new Pagination(0, 0, max(1, (int) $params->get('display_num', 20)));
    }

    /** @return list<object> */
    public function getItems(): array
    {
        return [];
    }

    /** @return list<object> */
    public function getItem(): array
    {
        return [];
    }

    public function getChildren()
    {
        return null;
    }

    public function getParent()
    {
        return null;
    }

    public function getPagination(): Pagination
    {
        return $this->pagination;
    }
}
