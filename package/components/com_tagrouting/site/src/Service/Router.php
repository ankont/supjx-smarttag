<?php

declare(strict_types=1);

namespace SuperSoft\Component\TagRouting\Site\Service;

use Joomla\CMS\Component\Router\RouterBase;

final class Router extends RouterBase
{
    public function preprocess($query)
    {
        return $query;
    }

    public function build(&$query)
    {
        $item = !empty($query['Itemid']) ? $this->menu->getItem((int) $query['Itemid']) : $this->menu->getActive();

        if (
            $item !== null
            && ($item->query['option'] ?? null) === 'com_tagrouting'
            && ($item->query['view'] ?? null) === 'tags'
            && ($query['view'] ?? 'tags') === 'tags'
        ) {
            unset($query['view']);
        }

        unset($query['layout']);

        return [];
    }

    public function parse(&$segments)
    {
        return ['view' => 'tags'];
    }
}
