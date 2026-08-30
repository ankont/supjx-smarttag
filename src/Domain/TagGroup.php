<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Domain;

use InvalidArgumentException;

final readonly class TagGroup
{
    /** @var list<TagNode> */
    public array $tags;

    /**
     * @param iterable<TagNode> $tags
     */
    public function __construct(iterable $tags)
    {
        $unique = [];

        foreach ($tags as $tag) {
            $unique[$tag->id] = $tag;
        }

        if ($unique === []) {
            throw new InvalidArgumentException('A tag group cannot be empty.');
        }

        ksort($unique, SORT_NUMERIC);
        $this->tags = array_values($unique);
    }

    /** @return list<int> */
    public function ids(): array
    {
        return array_map(static fn (TagNode $tag): int => $tag->id, $this->tags);
    }

    public function segment(): string
    {
        return implode(',', array_map(static fn (TagNode $tag): string => $tag->alias, $this->tags));
    }
}
