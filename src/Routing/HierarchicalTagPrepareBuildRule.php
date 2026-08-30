<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Routing;

use Joomla\CMS\Router\SiteRouter;
use Joomla\CMS\Uri\Uri;

final class HierarchicalTagPrepareBuildRule
{
    public function __invoke(SiteRouter &$router, Uri &$uri): void
    {
        $query = $uri->getQuery(true);

        if (($query['option'] ?? null) !== 'com_tags' || ($query['view'] ?? null) !== 'tag') {
            return;
        }

        $values = is_array($query['id'] ?? null) ? $query['id'] : [$query['id'] ?? null];
        $ids = array_values(array_unique(array_filter(array_map('intval', $values), static fn (int $id): bool => $id > 0)));

        if (count($ids) !== 1) {
            return;
        }

        $uri->setVar(TagPageState::REQUEST_KEY, $ids[0]);
    }
}
