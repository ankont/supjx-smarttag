<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Tag;

final class TagHierarchyResolver
{
    /**
     * @param list<string> $aliases
     * @param list<TagHierarchyNode> $nodes
     */
    public function resolve(array $aliases, array $nodes, string $language): ?ResolvedTagPage
    {
        if ($aliases === [] || in_array('', $aliases, true)) {
            return null;
        }

        $path = [];
        $parentId = 1;

        foreach ($aliases as $alias) {
            $candidates = array_values(array_filter(
                $nodes,
                static fn (TagHierarchyNode $node): bool => $node->parentId === $parentId && $node->alias === $alias,
            ));
            $exact = array_values(array_filter(
                $candidates,
                static fn (TagHierarchyNode $node): bool => $node->language === $language,
            ));

            if ($exact !== []) {
                $candidates = $exact;
            } else {
                $candidates = array_values(array_filter(
                    $candidates,
                    static fn (TagHierarchyNode $node): bool => $node->language === '*',
                ));
            }

            if (count($candidates) !== 1) {
                return null;
            }

            $path[] = $candidates[0];
            $parentId = $candidates[0]->id;
        }

        return new ResolvedTagPage($path);
    }
}
