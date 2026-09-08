<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Domain;

use InvalidArgumentException;

final readonly class TagPath
{
    /** @var list<TagNode> */
    public array $tags;

    /** @param iterable<TagNode> $tags */
    public function __construct(iterable $tags)
    {
        $this->tags = array_values(iterator_to_array((static function () use ($tags): iterable {
            yield from $tags;
        })(), false));

        if ($this->tags === []) {
            throw new InvalidArgumentException('A tag path cannot be empty.');
        }
    }

    public function leaf(): TagNode
    {
        return $this->tags[array_key_last($this->tags)];
    }

    public function segment(): string
    {
        return implode('/', array_map(static fn (TagNode $tag): string => $tag->alias, $this->tags));
    }
}
