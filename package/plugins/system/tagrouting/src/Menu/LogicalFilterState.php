<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Menu;

use SuperSoft\Plugin\System\TagRouting\Domain\SerializedExpression;

final class LogicalFilterState
{
    public function __construct(
        private readonly SerializedExpression $serializer,
        private readonly bool $allowFacetedState = true,
    ) {
    }

    /**
     * @param array<string,mixed> $values
     * @return array{groups:list<list<int>>,extra:array<string,list<string>>}
     */
    public function from(array $values, bool $isTagsComponent = false): array
    {
        $serialized = $this->allowFacetedState
            ? $this->serializer->decode($values[SerializedExpression::REQUEST_KEY] ?? null)
            : null;

        if ($serialized !== null) {
            $groups = $serialized['groups'];
        } else {
            $rawTags = $values['filter_tag'] ?? $values['tags'] ?? $values['tag_ids'] ?? null;

            if ($rawTags === null && $isTagsComponent) {
                $rawTags = $values['id'] ?? null;
            }

            $ids = $this->ids($rawTags);
            $matchAll = (bool) ($values['tag_list_all'] ?? $values['match_all'] ?? false);
            $groups = $matchAll ? array_map(static fn (int $id): array => [$id], $ids) : ($ids === [] ? [] : [$ids]);
        }

        foreach ($groups as &$group) {
            sort($group, SORT_NUMERIC);
        }
        unset($group);
        usort($groups, static fn (array $a, array $b): int => implode(',', $a) <=> implode(',', $b));

        $extra = [];

        foreach (['types', 'tag_list_language_filter'] as $key) {
            if (!array_key_exists($key, $values)) {
                continue;
            }

            $normalized = $this->strings($values[$key]);

            if ($normalized !== []) {
                $extra[$key] = $normalized;
            }
        }

        ksort($extra);

        return ['groups' => $groups, 'extra' => $extra];
    }

    /** @return list<int> */
    private function ids(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/\s*,\s*/', trim($value), -1, PREG_SPLIT_NO_EMPTY);
        } elseif (!is_array($value) && $value !== null) {
            $value = [$value];
        }

        $ids = [];

        foreach ($value ?? [] as $candidate) {
            $id = (int) $candidate;

            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        sort($ids, SORT_NUMERIC);

        return array_values($ids);
    }

    /** @return list<string> */
    private function strings(mixed $value): array
    {
        $values = is_array($value) ? $value : [$value];
        $result = array_values(array_unique(array_filter(array_map('strval', $values), 'strlen')));
        sort($result, SORT_STRING);

        return $result;
    }
}
