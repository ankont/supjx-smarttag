<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Routing;

final class FacetDelimiter
{
    public const CANONICAL = '!';

    /** @var list<string> */
    private const ACCEPTED = [self::CANONICAL, '!filter', '!tag'];

    /**
     * The core router must have consumed the complete real route already, so a
     * delimiter is valid only as the first remaining segment.
     *
     * @param list<string> $segments
     * @return array{segments:list<string>,canonical:bool}|null
     */
    public function extract(array $segments): ?array
    {
        if (count($segments) < 2 || !in_array($segments[0], self::ACCEPTED, true)) {
            return null;
        }

        return [
            'segments' => array_values(array_slice($segments, 1)),
            'canonical' => $segments[0] === self::CANONICAL,
        ];
    }

    /**
     * @param list<string> $expressionSegments
     * @return list<string>
     */
    public function canonicalSegments(array $expressionSegments): array
    {
        return [self::CANONICAL, ...$expressionSegments];
    }
}
