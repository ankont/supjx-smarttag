<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Domain;

final class TagPathResolver
{
    /**
     * @param list<TagNode> $allowedNodes
     */
    public function resolve(ParsedFacetExpression $expression, array $allowedNodes): ?FacetExpression
    {
        if ($allowedNodes === []) {
            return null;
        }

        $groups = [];

        foreach ($expression->groups as $paths) {
            $resolvedPaths = [];

            foreach ($paths as $aliases) {
                $path = [];
                $parent = null;

                foreach ($aliases as $alias) {
                    $candidates = array_values(array_filter(
                        $allowedNodes,
                        static fn (TagNode $candidate): bool => $candidate->alias === $alias
                            && ($parent === null || $candidate->parentId === $parent->id),
                    ));
                    $candidates = $this->unique($candidates);

                    if (count($candidates) !== 1) {
                        return null;
                    }

                    $parent = $candidates[0];
                    $path[] = $parent;
                }

                $resolvedPaths[] = new TagPath($path);
            }

            $groups[] = new FacetGroup($resolvedPaths);
        }

        return new FacetExpression($groups);
    }

    /** @param list<TagNode> $nodes @return list<TagNode> */
    private function unique(array $nodes): array
    {
        $unique = [];

        foreach ($nodes as $node) {
            $unique[$node->id] = $node;
        }

        return array_values($unique);
    }
}
