<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Domain;

/** Canonical URLs for resolved exact-membership AND-of-OR expressions. */
final class FacetExpressionCanonicalizer
{
    /** @param list<TagNode> $allowedNodes */
    public function normalize(FacetExpression $expression, array $allowedNodes): FacetExpression
    {
        $nodes = [];
        foreach ($allowedNodes as $node) {
            $nodes[$node->id] = $node;
        }
        $paths = [];
        foreach ($expression->groups as $group) {
            foreach ($group->paths as $path) {
                $leaf = $path->leaf();
                $chain = [$leaf];
                $seen = [$leaf->id => true];
                while (isset($nodes[$chain[0]->parentId]) && !isset($seen[$chain[0]->parentId])) {
                    $parent = $nodes[$chain[0]->parentId];
                    array_unshift($chain, $parent);
                    $seen[$parent->id] = true;
                }
                // Shortest suffix with a unique first alias in the current scope.
                for ($length = 1; $length <= count($chain); ++$length) {
                    $candidate = array_slice($chain, -$length);
                    $matches = array_filter($nodes, static fn (TagNode $node): bool => $node->alias === $candidate[0]->alias);
                    if (count($matches) === 1) {
                        $paths[$leaf->id] = new TagPath($candidate);
                        break;
                    }
                }
                if (!isset($paths[$leaf->id])) {
                    throw new \InvalidArgumentException('No unambiguous canonical tag path in scope.');
                }
            }
        }
        $groups = [];
        foreach ((new TagExpressionNormalizer())->normalize($expression->tags())->groups as $group) {
            $options = array_map(static fn (TagNode $tag): TagPath => $paths[$tag->id], $group->tags);
            usort($options, static fn (TagPath $a, TagPath $b): int => strcmp($a->segment(), $b->segment()));
            $groups[] = new FacetGroup($options);
        }
        usort($groups, static fn (FacetGroup $a, FacetGroup $b): int => strcmp($a->segment(), $b->segment()));

        return new FacetExpression($groups);
    }
}
