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

        return $databases[$db] ??= new self($db);
    }

    public static function resetForApplication(object $app): void
    {
        if (self::$instances !== null) { unset(self::$instances[$app]); }
    }

    /** @var array<string, array{roots:list<TagNode>,nodes:list<TagNode>}> */
    private array $cache = [];

    public function __construct(private readonly DatabaseInterface $db)
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
            ->where($this->db->quoteName('m.content_item_id') . ' = :categoryId')
            ->where($this->db->quoteName('m.type_alias') . ' = ' . $this->db->quote('com_content.category'))
            ->where($this->db->quoteName('t.published') . ' = 1')
            ->whereIn($this->db->quoteName('t.access'), $viewLevels, ParameterType::INTEGER)
            ->whereIn($this->db->quoteName('t.language'), ['*', $language], ParameterType::STRING)
            ->order($this->db->quoteName('t.lft'))
            ->bind(':categoryId', $categoryId, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $rootRows = $this->db->loadObjectList() ?: [];
        $roots = [];

        foreach ($rootRows as $row) {
            $root = $this->node($row, (int) $row->id);
            $roots[$root->id] = $root;
        }

        if ($roots === []) {
            return $this->cache[$key] = ['roots' => [], 'nodes' => []];
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
        return $this->cache[$key] = [
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
