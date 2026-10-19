<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Tags;

/** @internal SQL uses fixed identifiers and caller-quoted string literals. */
final class PublicationQueryGuard
{
    /** @param list<int> $viewLevels */
    public static function condition(string $articleType, string $categoryType, string $now, string $nullDate, array $viewLevels): string
    {
        $levels = implode(',', array_map('intval', $viewLevels)) ?: '-1';
        $categoryVisibility = "NOT EXISTS (SELECT 1 FROM #__categories AS supjx_ancestor"
            . " WHERE supjx_ancestor.lft <= supjx_category.lft"
            . " AND supjx_ancestor.rgt >= supjx_category.rgt"
            . " AND supjx_ancestor.id <> 1"
            . " AND (supjx_ancestor.published <> 1 OR supjx_ancestor.access NOT IN ($levels)))";
        $category = "SELECT 1 FROM #__categories AS supjx_category"
            . " WHERE supjx_category.id = supjx_article.catid"
            . " AND supjx_category.extension = 'com_content' AND $categoryVisibility";
        $article = "SELECT 1 FROM #__content AS supjx_article"
            . " WHERE supjx_article.id = m.content_item_id"
            . " AND supjx_article.state = 1 AND supjx_article.access IN ($levels)"
            . " AND (supjx_article.publish_up IS NULL OR supjx_article.publish_up = $nullDate OR supjx_article.publish_up <= $now)"
            . " AND (supjx_article.publish_down IS NULL OR supjx_article.publish_down = $nullDate OR supjx_article.publish_down >= $now)"
            . " AND EXISTS ($category)";
        $taggedCategory = "SELECT 1 FROM #__categories AS supjx_category"
            . " WHERE supjx_category.id = m.content_item_id"
            . " AND supjx_category.extension = 'com_content' AND $categoryVisibility";

        return "((m.type_alias <> $articleType OR EXISTS ($article))"
            . " AND (m.type_alias <> $categoryType OR EXISTS ($taggedCategory)))";
    }
}
