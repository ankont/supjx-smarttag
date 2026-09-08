<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Tag;

final readonly class ResolvedTagPage
{
    /** @var list<TagHierarchyNode> */
    public array $path;

    /** @param iterable<TagHierarchyNode> $path */
    public function __construct(iterable $path)
    {
        $this->path = array_values(iterator_to_array((static function () use ($path): iterable {
            yield from $path;
        })(), false));
    }

    public function id(): int
    {
        return $this->path[array_key_last($this->path)]->id;
    }

    /** @return list<string> */
    public function aliases(): array
    {
        return array_map(static fn (TagHierarchyNode $node): string => $node->alias, $this->path);
    }
}
