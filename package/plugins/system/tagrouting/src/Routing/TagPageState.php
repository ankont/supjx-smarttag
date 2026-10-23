<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Routing;

final class TagPageState
{
    public const REQUEST_KEY = '__tagrouting_tag';
    public const SCOPE_KEY = '__tagrouting_scope';

    private static ?\WeakMap $parsedContexts = null;

    /** A validated marker request without a menu must not inherit a scoped home menu. */
    public static function recordContext(object $menu, ?int $itemId): void
    {
        self::$parsedContexts ??= new \WeakMap();
        self::$parsedContexts[$menu] = ['item' => $itemId];
    }

    public static function isUnboundMarker(object $menu): bool
    {
        return isset(self::$parsedContexts[$menu]) && self::$parsedContexts[$menu]['item'] === null;
    }

    public static function reset(object $menu): void
    {
        if (self::$parsedContexts !== null) {
            unset(self::$parsedContexts[$menu]);
        }
    }

    public static function id(mixed $value): ?int
    {
        if (!is_scalar($value)) {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }
}
