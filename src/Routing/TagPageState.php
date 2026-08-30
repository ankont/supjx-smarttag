<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Routing;

final class TagPageState
{
    public const REQUEST_KEY = '__tagrouting_tag';

    public static function id(mixed $value): ?int
    {
        if (!is_scalar($value)) {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }
}
