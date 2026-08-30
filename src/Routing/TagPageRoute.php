<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Routing;

use Joomla\CMS\Router\Route;
use SuperSoft\Plugin\System\TagRouting\Configuration\RoutingFeatures;

final class TagPageRoute
{
    /**
     * Returns Joomla's normal tag route when the feature is disabled and the
     * configured canonical hierarchical route when it is enabled.
     */
    public static function get(int $tagId, ?string $language = null): string
    {
        if ($tagId < 1) {
            return '';
        }

        $query = [
            'option' => 'com_tags',
            'view' => 'tag',
            'id' => [$tagId],
        ];

        if (RoutingFeatures::fromPlugin()->hierarchicalTags) {
            $query[TagPageState::REQUEST_KEY] = $tagId;
        }

        if ($language !== null && $language !== '*') {
            $query['lang'] = $language;
        }

        return Route::_($query);
    }
}
