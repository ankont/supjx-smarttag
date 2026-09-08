<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Domain;

final readonly class TagExpression
{
    /** @var list<TagGroup> */
    public array $groups;

    /**
     * @param iterable<TagGroup> $groups
     */
    public function __construct(iterable $groups)
    {
        $this->groups = array_values(iterator_to_array((static function () use ($groups): iterable {
            yield from $groups;
        })(), false));
    }

    public function isEmpty(): bool
    {
        return $this->groups === [];
    }

    /** @return list<list<int>> */
    public function ids(): array
    {
        return array_map(static fn (TagGroup $group): array => $group->ids(), $this->groups);
    }

    /** @return list<string> */
    public function segments(): array
    {
        return explode('/', $this->expression());
    }

    public function expression(): string
    {
        return implode('+', array_map(static fn (TagGroup $group): string => $group->segment(), $this->groups));
    }
}
