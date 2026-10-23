<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Tag;

/** Native UCM type IDs; an empty configured list means all content types. */
final class ContentTypeScope
{
    /** @return list<int> */
    public static function normalize(mixed $values): array
    {
        $ids = [];
        foreach ((array) $values as $value) {
            if (!is_int($value) && !is_string($value)) {
                continue;
            }
            foreach (explode(',', (string) $value) as $id) {
                $id = trim($id);
                if (ctype_digit($id) && (int) $id > 0) {
                    $ids[] = (int) $id;
                }
            }
        }
        $ids = array_values(array_unique($ids));
        sort($ids, SORT_NUMERIC);
        return $ids;
    }
}
