<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Domain;

use JsonException;

final class SerializedExpression
{
    public const REQUEST_KEY = '__tagrouting';

    public function encode(int $categoryId, TagExpression $public, TagExpression $effective): string
    {
        return json_encode(
            [
                'v' => 1,
                'category' => $categoryId,
                'segments' => $public->segments(),
                'groups' => $effective->ids(),
                'resolved' => array_map(
                    static fn (TagGroup $group): array => array_map(
                        static fn (TagNode $tag): array => [
                            'id' => $tag->id,
                            'alias' => $tag->alias,
                            'root' => $tag->rootId,
                        ],
                        $group->tags,
                    ),
                    $public->groups,
                ),
            ],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    /**
     * @return array{category:int,segments:list<string>,groups:list<list<int>>,resolved:list<list<array{id:int,alias:string,root:int>>>}|null
     */
    public function decode(mixed $value): ?array
    {
        if (!is_string($value) || $value === '' || strlen($value) > 8192) {
            return null;
        }

        try {
            $data = json_decode($value, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (!is_array($data) || ($data['v'] ?? null) !== 1 || !is_int($data['category'] ?? null)) {
            return null;
        }

        $segments = $this->stringList($data['segments'] ?? null);
        $groups = $this->integerGroups($data['groups'] ?? null);
        $resolved = $this->resolvedGroups($data['resolved'] ?? null);

        if ($segments === null || $groups === null || $groups === [] || $resolved === null) {
            return null;
        }

        return ['category' => $data['category'], 'segments' => $segments, 'groups' => $groups, 'resolved' => $resolved];
    }

    /** @return list<string>|null */
    private function stringList(mixed $values): ?array
    {
        if (!is_array($values)) {
            return null;
        }

        foreach ($values as $value) {
            if (!is_string($value) || $value === '' || strlen($value) > 255) {
                return null;
            }
        }

        return array_values($values);
    }

    /** @return list<list<int>>|null */
    private function integerGroups(mixed $groups): ?array
    {
        if (!is_array($groups) || count($groups) > 16) {
            return null;
        }

        $result = [];

        foreach ($groups as $group) {
            if (!is_array($group) || $group === [] || count($group) > 32) {
                return null;
            }

            $ids = [];

            foreach ($group as $id) {
                if (!is_int($id) || $id < 1) {
                    return null;
                }

                $ids[$id] = $id;
            }

            $result[] = array_values($ids);
        }

        return $result;
    }

    /** @return list<list<array{id:int,alias:string,root:int}>>|null */
    private function resolvedGroups(mixed $groups): ?array
    {
        if (!is_array($groups) || count($groups) > 16) {
            return null;
        }

        $result = [];

        foreach ($groups as $group) {
            if (!is_array($group) || count($group) > 32) {
                return null;
            }

            $items = [];

            foreach ($group as $item) {
                if (
                    !is_array($item)
                    || !is_int($item['id'] ?? null)
                    || !is_int($item['root'] ?? null)
                    || !is_string($item['alias'] ?? null)
                ) {
                    return null;
                }

                $items[] = ['id' => $item['id'], 'alias' => $item['alias'], 'root' => $item['root']];
            }

            $result[] = $items;
        }

        return $result;
    }
}
