<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Domain;

final readonly class ParsedFacetExpression
{
    /** @var list<list<list<string>>> */
    public array $groups;

    /** @param iterable<iterable<iterable<string>>> $groups */
    public function __construct(
        public string $canonical,
        iterable $groups,
    ) {
        $result = [];

        foreach ($groups as $group) {
            $paths = [];

            foreach ($group as $path) {
                $paths[] = array_values(iterator_to_array((static function () use ($path): iterable {
                    yield from $path;
                })(), false));
            }

            $result[] = $paths;
        }

        $this->groups = $result;
    }
}
