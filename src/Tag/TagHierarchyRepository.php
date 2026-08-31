<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Tag;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

final class TagHierarchyRepository
{
    /** @var array<string,ResolvedTagPage|null> */
    private array $pathCache = [];

    /** @var array<string,ResolvedTagPage|null> */
    private array $idCache = [];

    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly TagHierarchyResolver $resolver,
    ) {
    }

    /** @param list<string> $aliases @param list<int> $viewLevels */
    public function resolvePath(array $aliases, array $viewLevels, string $language): ?ResolvedTagPage
    {
        $viewLevels = $this->levels($viewLevels);
        $cacheKey = implode('/', $aliases) . ':' . implode(',', $viewLevels) . ':' . $language;

        if (array_key_exists($cacheKey, $this->pathCache)) {
            return $this->pathCache[$cacheKey];
        }

        if ($aliases === [] || $viewLevels === []) {
            return $this->pathCache[$cacheKey] = null;
        }

        $query = $this->baseQuery($viewLevels, $language)
            ->whereIn($this->db->quoteName('t.alias'), array_values(array_unique($aliases)), ParameterType::STRING)
            ->order($this->db->quoteName('t.lft'));

        $this->db->setQuery($query);
        $nodes = array_map($this->node(...), $this->db->loadObjectList() ?: []);

        return $this->pathCache[$cacheKey] = $this->resolver->resolve($aliases, $nodes, $language);
    }

    /** @param list<int> $viewLevels */
    public function pathForId(int $tagId, array $viewLevels, string $language): ?ResolvedTagPage
    {
        $viewLevels = $this->levels($viewLevels);
        $cacheKey = $tagId . ':' . implode(',', $viewLevels) . ':' . $language;

        if (array_key_exists($cacheKey, $this->idCache)) {
            return $this->idCache[$cacheKey];
        }

        if ($tagId < 1 || $viewLevels === []) {
            return $this->idCache[$cacheKey] = null;
        }

        $targetQuery = $this->baseQuery($viewLevels, $language)
            ->where($this->db->quoteName('t.id') . ' = :tagId')
            ->bind(':tagId', $tagId, ParameterType::INTEGER);
        $this->db->setQuery($targetQuery);
        $targetRow = $this->db->loadObject();

        if ($targetRow === null) {
            return $this->idCache[$cacheKey] = null;
        }

        $target = $this->node($targetRow);
        // DatabaseInterface::bind() accepts its value by reference. Readonly
        // object properties cannot be passed by reference, so bind local
        // scalar copies of the nested-set bounds instead.
        $targetLft = $target->lft;
        $targetRgt = $target->rgt;
        $query = $this->baseQuery($viewLevels, $language)
            ->where($this->db->quoteName('t.lft') . ' <= :targetLft')
            ->where($this->db->quoteName('t.rgt') . ' >= :targetRgt')
            ->where($this->db->quoteName('t.id') . ' <> 1')
            ->order($this->db->quoteName('t.lft'))
            ->bind(':targetLft', $targetLft, ParameterType::INTEGER)
            ->bind(':targetRgt', $targetRgt, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $nodes = array_map($this->node(...), $this->db->loadObjectList() ?: []);
        $byId = [];

        foreach ($nodes as $node) {
            $byId[$node->id] = $node;
        }

        $path = [];
        $current = $byId[$tagId] ?? null;

        while ($current !== null) {
            array_unshift($path, $current);

            if ($current->parentId === 1) {
                break;
            }

            $current = $byId[$current->parentId] ?? null;
        }

        $resolved = $path !== [] && $path[0]->parentId === 1 ? new ResolvedTagPage($path) : null;

        if ($resolved === null || $resolved->id() !== $tagId) {
            return $this->idCache[$cacheKey] = null;
        }

        return $this->idCache[$cacheKey] = $resolved;
    }

    /** @param list<int> $viewLevels */
    private function baseQuery(array $viewLevels, string $language): object
    {
        return $this->db->createQuery()
            ->select([
                $this->db->quoteName('t.id'),
                $this->db->quoteName('t.alias'),
                $this->db->quoteName('t.parent_id'),
                $this->db->quoteName('t.lft'),
                $this->db->quoteName('t.rgt'),
                $this->db->quoteName('t.language'),
            ])
            ->from($this->db->quoteName('#__tags', 't'))
            ->where($this->db->quoteName('t.published') . ' = 1')
            ->whereIn($this->db->quoteName('t.access'), $viewLevels, ParameterType::INTEGER)
            ->whereIn($this->db->quoteName('t.language'), array_values(array_unique(['*', $language])), ParameterType::STRING);
    }

    private function node(object $row): TagHierarchyNode
    {
        return new TagHierarchyNode(
            (int) $row->id,
            (string) $row->alias,
            (int) $row->parent_id,
            (int) $row->lft,
            (int) $row->rgt,
            (string) $row->language,
        );
    }

    /** @param list<int> $levels @return list<int> */
    private function levels(array $levels): array
    {
        $levels = array_values(array_unique(array_filter(array_map('intval', $levels), static fn (int $id): bool => $id > 0)));
        sort($levels, SORT_NUMERIC);

        return $levels;
    }
}
