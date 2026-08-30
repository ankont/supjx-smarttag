<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Domain;

final readonly class FacetExpression
{
    /** @var list<FacetGroup> */
    public array $groups;

    /** @param iterable<FacetGroup> $groups */
    public function __construct(iterable $groups)
    {
        $this->groups = array_values(iterator_to_array((static function () use ($groups): iterable {
            yield from $groups;
        })(), false));
    }

    public function tags(): TagExpression
    {
        return new TagExpression(array_map(static fn (FacetGroup $group): TagGroup => $group->tags(), $this->groups));
    }

    public function expression(): string
    {
        return implode('+', array_map(static fn (FacetGroup $group): string => $group->segment(), $this->groups));
    }
}
