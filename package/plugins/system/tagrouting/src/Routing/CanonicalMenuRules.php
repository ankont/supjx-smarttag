<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Routing;

use Joomla\CMS\Component\Router\RouterView;
use SuperSoft\Plugin\System\TagRouting\Menu\MenuTagContextResolver;

final class CanonicalMenuRules extends \Joomla\CMS\Component\Router\Rules\MenuRules
{
    public function __construct(RouterView $router, private readonly MenuTagContextResolver $contexts)
    {
        parent::__construct($router);
    }

    public function preprocess(&$query)
    {
        if ($this->contexts->canonicalize($query)) {
            return;
        }

        parent::preprocess($query);
    }
}
