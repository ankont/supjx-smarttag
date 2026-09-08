<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Routing;

final class TagPageRoute
{
    /**
     * Returns Joomla's normal tag route when the feature is disabled and the
     * configured canonical hierarchical route when it is enabled.
     */
    public static function get(int $tagId, ?string $language = null): string
    {
        return TagRoute::forTag($tagId, $language);
    }
}
