<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Tag;

use Joomla\CMS\Menu\AbstractMenu;

final readonly class TagMenuContextResolver
{
    public function __construct(private AbstractMenu $menu)
    {
    }

    public function find(int $tagId, string $language): ?int
    {
        $matches = [];

        foreach ($this->menu->getItems('component', 'com_tags') ?: [] as $item) {
            if (!in_array((string) $item->language, ['*', $language], true)) {
                continue;
            }

            $query = $item->query ?? [];
            $score = null;

            if (($query['view'] ?? null) === 'tag' && in_array($tagId, $this->ids($query['id'] ?? null), true)) {
                $score = 0;
            } elseif (($query['view'] ?? null) === 'tags' && (int) ($query['parent_id'] ?? 0) === 0) {
                $score = 1;
            }

            if ($score !== null) {
                $matches[] = [$score, (string) $item->language === $language ? 0 : 1, (int) $item->id];
            }
        }

        usort($matches, static fn (array $a, array $b): int => $a <=> $b);

        return $matches[0][2] ?? null;
    }

    /** @return list<int> */
    private function ids(mixed $value): array
    {
        $values = is_array($value) ? $value : [$value];

        return array_values(array_filter(array_map('intval', $values), static fn (int $id): bool => $id > 0));
    }
}
