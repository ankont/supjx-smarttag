<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Tags;

use Joomla\CMS\Router\Exception\RouteNotFoundException;

/** Public, exact-match filters; never replace native/menu type restrictions. */
final readonly class TagQueryFilter
{
    private function __construct(public array $types, public ?int $category) {}

    public static function fromQuery(array $query): self
    {
        $raw = $query['content-types'] ?? null;
        $types = [];
        $category = $query['content-category'] ?? null;
        if ($raw !== null) {
            if (!is_string($raw) || strlen($raw) > 2048) {
                throw new RouteNotFoundException('Invalid content types.');
            }
            $types = explode(',', $raw);
            if (count($types) > 32) {
                throw new RouteNotFoundException('Too many content types.');
            }
            foreach ($types as $type) {
                if (strlen($type) > 100 || !preg_match('/\A[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*\z/D', $type)) {
                    throw new RouteNotFoundException('Invalid content type.');
                }
            }
            $types = array_values(array_unique($types));
            sort($types, SORT_STRING);
        }
        if ($category !== null && ((!is_string($category) && !is_int($category))
            || !preg_match('/\A[1-9][0-9]*\z/D', (string) $category)
            || filter_var($category, FILTER_VALIDATE_INT) === false)) {
            throw new RouteNotFoundException('Invalid content category.');
        }
        return new self($types, $category === null ? null : (int) $category);
    }

    public function parameters(): array
    {
        return array_filter(['content-types' => $this->types === [] ? null : implode(',', $this->types), 'content-category' => $this->category],
            static fn ($value): bool => $value !== null);
    }

    public function apply(object $query, object $db, array $levels, string $language): void
    {
        if ($this->types !== []) {
            $quoted = implode(',', array_map($db->quote(...), $this->types));
            $lookup = $db->createQuery()->select('COUNT(DISTINCT type_alias)')->from('#__content_types')
                ->where('type_alias IN (' . $quoted . ')');
            if ((int) $db->setQuery($lookup)->loadResult() !== count($this->types)) {
                throw new RouteNotFoundException('Content type not found.');
            }
            $query->where('m.type_alias IN (' . $quoted . ')');
        }
        if ($this->category !== null) {
            $allowed = implode(',', array_map('intval', $levels)) ?: '-1';
            $lookup = $db->createQuery()->select('c.id')->from('#__categories AS c')
                ->where('c.id = ' . $this->category)->where("c.extension = 'com_content'")
                ->where('c.id > 1')->where('c.published = 1')
                ->where('c.access IN (' . $allowed . ')')
                ->where('c.language IN (' . $db->quote('*') . ',' . $db->quote($language) . ')')
                ->where('NOT EXISTS (SELECT 1 FROM #__categories AS ancestor'
                    . ' WHERE ancestor.lft <= c.lft AND ancestor.rgt >= c.rgt AND ancestor.id <> 1'
                    . ' AND (ancestor.published <> 1 OR ancestor.access NOT IN (' . $allowed . ')))');
            if (!$db->setQuery($lookup)->loadResult()) {
                throw new RouteNotFoundException('Content category not found.');
            }
            $query->where("m.type_alias = 'com_content.article'")
                ->where('EXISTS (SELECT 1 FROM #__content AS supjx_filter_article'
                    . ' WHERE supjx_filter_article.id = m.content_item_id'
                    . ' AND supjx_filter_article.catid = ' . $this->category . ')');
        }
    }
}
