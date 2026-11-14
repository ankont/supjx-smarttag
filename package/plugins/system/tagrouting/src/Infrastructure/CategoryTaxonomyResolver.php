<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Infrastructure;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use SuperSoft\Plugin\System\TagRouting\Domain\TagNode;

final class CategoryTaxonomyResolver
{
    /** Application and database identities bound cache lifetime and isolation. */
    private static ?\WeakMap $instances = null;

    public static function forApplication(object $app, DatabaseInterface $db): self
    {
        self::$instances ??= new \WeakMap();
        $databases = self::$instances[$app] ??= new \WeakMap();

        return $databases[$db] ??= new self($db, \SuperSoft\Plugin\System\TagRouting\Configuration\FacetSettings::fromPlugin()->scope);
    }

    public static function resetForApplication(object $app): void
    {
        if (self::$instances !== null) { unset(self::$instances[$app]); }
    }

    /** @var array<string, array{roots:list<TagNode>,nodes:list<TagNode>}> */
    private array $cache = [];

    public function __construct(private readonly DatabaseInterface $db, private readonly string $mode = 'category')
    {
    }

    /**
     * Returns each node with the configured root that owns it. A nested configured
     * root wins over a broader configured root, preserving useful UI grouping.
     *
     * @param list<int> $viewLevels
     * @return array{roots:list<TagNode>,nodes:list<TagNode>}
     */
    public function resolve(int $categoryId, array $viewLevels, string $language): array
    {
        $viewLevels = array_values(array_unique(array_map('intval', $viewLevels)));
        sort($viewLevels, SORT_NUMERIC);
        $key = $categoryId . ':' . implode(',', $viewLevels) . ':' . $language;

        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        if ($categoryId < 1 || $viewLevels === []) {
            return $this->cache[$key] = ['roots' => [], 'nodes' => []];
        }

        if ($this->mode === 'all') {
            return $this->cache[$key] = $this->resolveRoots(null, $viewLevels, $language);
        }
        $categoryIds = [$categoryId];
        if ($this->mode === 'inherited') {
            $ancestors = $this->db->createQuery()->select('ancestor.id')->from('#__categories AS current_category')
                ->innerJoin('#__categories AS ancestor', 'ancestor.lft <= current_category.lft AND ancestor.rgt >= current_category.rgt')
                ->where('current_category.id = ' . $categoryId)->where("ancestor.extension = 'com_content'")
                ->where('ancestor.published = 1')->whereIn('ancestor.access', $viewLevels, ParameterType::INTEGER)
                ->whereIn('ancestor.language', ['*', $language], ParameterType::STRING);
            $categoryIds = array_map('intval', $this->db->setQuery($ancestors)->loadColumn() ?: []);
        }
        if ($categoryIds === []) {
            return $this->cache[$key] = ['roots' => [], 'nodes' => []];
        }
        $query = $this->db->createQuery()
            ->select([
                $this->db->quoteName('t.id'),
                $this->db->quoteName('t.alias'),
                $this->db->quoteName('t.parent_id'),
                $this->db->quoteName('t.lft'),
                $this->db->quoteName('t.rgt'),
                $this->db->quoteName('t.title'),
            ])
            ->from($this->db->quoteName('#__tags', 't'))
            ->innerJoin(
                $this->db->quoteName('#__contentitem_tag_map', 'm'),
                $this->db->quoteName('m.tag_id') . ' = ' . $this->db->quoteName('t.id'),
            )
            ->whereIn($this->db->quoteName('m.content_item_id'), $categoryIds, ParameterType::INTEGER)
            ->where($this->db->quoteName('m.type_alias') . ' = ' . $this->db->quote('com_content.category'))
            ->where($this->db->quoteName('t.published') . ' = 1')
            ->whereIn($this->db->quoteName('t.access'), $viewLevels, ParameterType::INTEGER)
            ->whereIn($this->db->quoteName('t.language'), ['*', $language], ParameterType::STRING)
            ->order($this->db->quoteName('t.lft'));

        $this->db->setQuery($query);
        $rootRows = $this->db->loadObjectList() ?: [];
        return $this->cache[$key] = $this->expand($rootRows, $viewLevels, $language);
    }

    /** null roots means all published/accessible tags; [] means no filters. */
    public function resolveRoots(?array $rootIds, array $viewLevels, string $language): array
    {
        $rootIds = $rootIds === null ? null : array_values(array_unique(array_filter(array_map('intval', $rootIds), static fn ($id) => $id > 1)));
        $key = 'roots:' . json_encode([$rootIds, $viewLevels, $language]);
        if (isset($this->cache[$key])) { return $this->cache[$key]; }
        if ($viewLevels === [] || $rootIds === []) { return ['roots' => [], 'nodes' => []]; }
        $query = $this->db->createQuery()->select('t.id,t.alias,t.parent_id,t.lft,t.rgt,t.title')->from('#__tags AS t')
            ->where('t.id > 1')->where('t.published = 1')
            ->whereIn('t.access', $viewLevels, ParameterType::INTEGER)
            ->whereIn('t.language', ['*', $language], ParameterType::STRING)->order('t.lft');
        if ($rootIds !== null) { $query->whereIn('t.id', $rootIds, ParameterType::INTEGER); }
        $rows = $this->db->setQuery($query)->loadObjectList() ?: [];
        // In all-tags mode every node is resolvable, including orphaned language branches.
        if ($rootIds === null) {
            $nodes = array_map(fn ($row) => $this->node($row, (int) $row->id), $rows);
            $ids = array_fill_keys(array_map(static fn ($node) => $node->id, $nodes), true);
            $roots = array_values(array_filter($nodes, static fn ($node) => !isset($ids[$node->parentId])));
            $nodes = array_map(function ($row) use ($roots) {
                $rootId = (int) $row->id;
                foreach ($roots as $root) {
                    if ($root->lft <= (int) $row->lft && $root->rgt >= (int) $row->rgt) { $rootId = $root->id; break; }
                }
                return $this->node($row, $rootId);
            }, $rows);
            return $this->cache[$key] = ['roots' => $roots, 'nodes' => $nodes];
        }
        return $this->cache[$key] = $this->expand($rows, $viewLevels, $language);
    }

    private function expand(array $rootRows, array $viewLevels, string $language): array
    {
        $roots = [];

        foreach ($rootRows as $row) {
            $root = $this->node($row, (int) $row->id);
            $roots[$root->id] = $root;
        }

        if ($roots === []) {
            return ['roots' => [], 'nodes' => []];
        }

        $ranges = [];

        foreach ($roots as $root) {
            $ranges[] = '(' . $this->db->quoteName('t.lft') . ' > ' . $root->lft
                . ' AND ' . $this->db->quoteName('t.rgt') . ' < ' . $root->rgt . ')';
        }

        $query = $this->db->createQuery()
            ->select([
                $this->db->quoteName('t.id'),
                $this->db->quoteName('t.alias'),
                $this->db->quoteName('t.parent_id'),
                $this->db->quoteName('t.lft'),
                $this->db->quoteName('t.rgt'),
                $this->db->quoteName('t.title'),
            ])
            ->from($this->db->quoteName('#__tags', 't'))
            ->where('(' . implode(' OR ', $ranges) . ')')
            ->where($this->db->quoteName('t.published') . ' = 1')
            ->whereIn($this->db->quoteName('t.access'), $viewLevels, ParameterType::INTEGER)
            ->whereIn($this->db->quoteName('t.language'), ['*', $language], ParameterType::STRING)
            ->order($this->db->quoteName('t.lft'));

        $this->db->setQuery($query);
        $rows = $this->db->loadObjectList() ?: [];
        $nodes = [];

        foreach ($rows as $row) {
            $owners = array_filter(
                $roots,
                static fn (TagNode $root): bool => $root->lft < (int) $row->lft && $root->rgt > (int) $row->rgt,
            );
            usort($owners, static fn (TagNode $a, TagNode $b): int => ($a->rgt - $a->lft) <=> ($b->rgt - $b->lft));

            if ($owners !== []) {
                $node = $this->node($row, $owners[0]->id);
                $nodes[$node->id] = $node;
            }
        }

        // Include configured roots in the resolvable public paths. This keeps
        // the URL aligned with the real Joomla hierarchy while preserving the
        // same leaf-tag filtering semantics.
        return [
            'roots' => array_values($roots),
            'nodes' => [...array_values($roots), ...array_values($nodes)],
        ];
    }

    private function node(object $row, int $rootId): TagNode
    {
        return new TagNode(
            (int) $row->id,
            (string) $row->alias,
            (int) $row->parent_id,
            (int) $row->lft,
            (int) $row->rgt,
            $rootId,
            (string) $row->title,
        );
    }
}
