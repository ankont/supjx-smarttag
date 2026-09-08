<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Routing;

final class FacetDelimiter
{
    public const CANONICAL = '!';

    /** @var list<string> */
    private array $accepted;

    /** @param list<string> $accepted */
    public function __construct(array $accepted = [self::CANONICAL, '!filter', '!tag'], private readonly string $canonical = self::CANONICAL)
    {
        $this->accepted = array_values(array_unique($accepted));
    }

    /**
     * The core router must have consumed the complete real route already, so a
     * delimiter is valid only as the first remaining segment.
     *
     * @param list<string> $segments
     * @return array{segments:list<string>,canonical:bool,marker:string}|null
     */
    public function extract(array $segments): ?array
    {
        if (count($segments) < 2 || !in_array($segments[0], $this->accepted, true)) {
            return null;
        }

        return [
            'segments' => array_values(array_slice($segments, 1)),
            'canonical' => $segments[0] === $this->canonical,
            'marker' => $segments[0],
        ];
    }

    /**
     * @param list<string> $expressionSegments
     * @return list<string>
     */
    public function canonicalSegments(array $expressionSegments): array
    {
        return [$this->canonical, ...$expressionSegments];
    }

    public function canonical(): string
    {
        return $this->canonical;
    }
}
