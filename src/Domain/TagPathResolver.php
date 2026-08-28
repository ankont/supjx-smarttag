<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Domain;

final class TagPathResolver
{
    /**
     * @param list<string> $segments
     * @param list<TagNode> $allowedNodes
     */
    public function resolve(array $segments, array $allowedNodes): ?TagExpression
    {
        if ($segments === [] || $allowedNodes === []) {
            return null;
        }

        $groups = [];
        /** @var list<TagNode> $context */
        $context = [];

        foreach ($segments as $rawSegment) {
            // Joomla's SiteRouter currently applies urldecode() to the whole path,
            // which turns a literal plus into a space. Tag aliases cannot contain
            // spaces, so restoring it here preserves the documented path grammar.
            $rawSegment = str_replace(' ', '+', trim($rawSegment));
            $aliases = explode('+', $rawSegment);

            if ($aliases === [] || in_array('', $aliases, true)) {
                return null;
            }

            $resolved = [];

            foreach ($aliases as $alias) {
                $candidates = $this->matches($alias, $allowedNodes, $context);

                if (count($candidates) !== 1) {
                    return null;
                }

                $resolved[$candidates[0]->id] = $candidates[0];
            }

            $group = new TagGroup(array_values($resolved));
            $groups[] = $group;
            $context = $group->tags;
        }

        return new TagExpression($groups);
    }

    /**
     * @param list<TagNode> $allowedNodes
     * @param list<TagNode> $context
     * @return list<TagNode>
     */
    private function matches(string $alias, array $allowedNodes, array $context): array
    {
        $all = array_values(array_filter(
            $allowedNodes,
            static fn (TagNode $node): bool => $node->alias === $alias,
        ));

        if ($context === []) {
            return $this->unique($all);
        }

        $scoped = array_values(array_filter(
            $all,
            static function (TagNode $node) use ($context): bool {
                foreach ($context as $parent) {
                    if ($parent->id !== $node->id && $parent->contains($node)) {
                        return true;
                    }
                }

                return false;
            },
        ));

        // No descendant match means a new AND facet may start under another root.
        return $this->unique($scoped !== [] ? $scoped : $all);
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
