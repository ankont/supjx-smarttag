<?php
declare(strict_types=1);
namespace SuperSoft\Plugin\System\TagRouting\Domain;

/** Expand SQL matching only; never change the user's public selections. */
final class FacetMembership
{
    public static function groups(array $groups, array $nodes, bool $descendants): array
    {
        $byId = [];
        foreach ($nodes as $node) { $byId[$node->id] = $node; }
        $result = [];
        foreach ($groups as $group) {
            $ids = [];
            foreach ($group as $id) {
                if (!isset($byId[$id])) { throw new \InvalidArgumentException('Tag outside permitted scope.'); }
                $ids[$id] = $id;
                if ($descendants) {
                    $parent = $byId[$id];
                    foreach ($nodes as $node) {
                        if ($node->lft > $parent->lft && $node->rgt < $parent->rgt) { $ids[$node->id] = $node->id; }
                    }
                }
            }
            ksort($ids, SORT_NUMERIC);
            $result[] = array_values($ids);
        }
        return $result;
    }
}
