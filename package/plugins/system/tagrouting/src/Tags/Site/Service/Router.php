<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Tags\Site\Service;

use Joomla\CMS\Factory;
use SuperSoft\Plugin\System\TagRouting\Routing\StandaloneTagScope;
use SuperSoft\Plugin\System\TagRouting\Routing\TagPageState;

final class Router extends \Joomla\Component\Tags\Site\Service\Router
{
    public function preprocess($query)
    {
        if (($query['view'] ?? null) === 'tag') {
            // Core may replace even an explicit active SmartTag Itemid with a
            // native Tagged Items menu. Capture its original semantics first.
            $query[TagPageState::SCOPE_KEY] = StandaloneTagScope::capture(Factory::getApplication()->getMenu(), $query);
        }
        return parent::preprocess($query);
    }
}
