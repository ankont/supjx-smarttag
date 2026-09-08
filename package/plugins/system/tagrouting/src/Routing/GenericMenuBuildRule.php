<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Routing;

use Joomla\CMS\Router\SiteRouter;
use Joomla\CMS\Uri\Uri;
use SuperSoft\Plugin\System\TagRouting\Menu\MenuTagContextResolver;

final readonly class GenericMenuBuildRule
{
    public function __construct(private MenuTagContextResolver $contexts)
    {
    }

    public function __invoke(SiteRouter &$router, Uri &$uri): void
    {
        $query = $uri->getQuery(true);

        if ($this->contexts->canonicalize($query)) {
            $uri->setQuery($query);
        }
    }
}
