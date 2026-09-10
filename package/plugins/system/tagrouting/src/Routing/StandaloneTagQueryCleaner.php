<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Routing;

/** @internal Query-state boundary for canonical standalone tag URLs. */
final class StandaloneTagQueryCleaner
{
    /** @var list<string> */
    private const CONTEXT_KEYS = [
        'option',
        'view',
        'id',
        'Itemid',
        'layout',
        'catid',
        'tmpl',
        'tag_id',
        'tag_preview',
    ];

    /**
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>
     */
    public function clean(array $query): array
    {
        foreach (self::CONTEXT_KEYS as $key) {
            unset($query[$key]);
        }

        return $query;
    }
}
