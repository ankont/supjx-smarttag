<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Domain;

use InvalidArgumentException;

final readonly class FacetGroup
{
    /** @var list<TagPath> */
    public array $paths;

    /** @param iterable<TagPath> $paths */
    public function __construct(iterable $paths)
    {
        $this->paths = array_values(iterator_to_array((static function () use ($paths): iterable {
            yield from $paths;
        })(), false));

        if ($this->paths === []) {
            throw new InvalidArgumentException('A facet group cannot be empty.');
        }
    }

    public function tags(): TagGroup
    {
        return new TagGroup(array_map(static fn (TagPath $path): TagNode => $path->leaf(), $this->paths));
    }

    public function segment(): string
    {
        return implode(',', array_map(static fn (TagPath $path): string => $path->segment(), $this->paths));
    }
}
