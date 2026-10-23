<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Routing;

use Joomla\CMS\Router\Route;
use SuperSoft\Plugin\System\TagRouting\Configuration\RoutingFeatures;

final class TagRoute
{
    /**
     * Builds one standalone tag-page URL. The router decides whether its
     * canonical base is a SupJx SmartTag Pages menu route or the canonical marker.
     */
    public static function forTag(int $tagId, ?string $language = null, ?int $itemId = null): string
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

        if ($itemId !== null && $itemId > 0) {
            $query['Itemid'] = $itemId;
        }

        if ($language !== null && $language !== '*') {
            $query['lang'] = $language;
        }

        return Route::_('index.php?' . http_build_query($query));
    }
}
