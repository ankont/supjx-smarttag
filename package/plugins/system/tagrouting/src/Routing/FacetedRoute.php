<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Routing;

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;
use SuperSoft\Plugin\System\TagRouting\Configuration\RoutingFeatures;
use SuperSoft\Plugin\System\TagRouting\Domain\FacetExpression;
use SuperSoft\Plugin\System\TagRouting\Domain\FacetGroup;
use SuperSoft\Plugin\System\TagRouting\Domain\SerializedExpression;
use SuperSoft\Plugin\System\TagRouting\Domain\TagExpression;
use SuperSoft\Plugin\System\TagRouting\Domain\TagExpressionNormalizer;
use SuperSoft\Plugin\System\TagRouting\Domain\TagNode;
use SuperSoft\Plugin\System\TagRouting\Domain\TagPath;
use SuperSoft\Plugin\System\TagRouting\Infrastructure\CategoryTaxonomyResolver;
use SuperSoft\Plugin\System\TagRouting\Tag\TagHierarchyRepository;
use SuperSoft\Plugin\System\TagRouting\Tag\TagHierarchyResolver;

final class FacetedRoute
{
    private static ?TagHierarchyRepository $tags = null;
    private static ?CategoryTaxonomyResolver $taxonomies = null;

    /**
     * Builds a category-first faceted URL from a tag ID. It never starts from
     * com_tags and therefore never leaks a contextual ?catid parameter.
     */
    public static function forTag(
        int $categoryId,
        int $tagId,
        ?int $itemId = null,
        ?string $language = null,
    ): string {
        if ($categoryId < 1 || $tagId < 1) {
            return '';
        }

        $app = Factory::getApplication();
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $viewLevels = array_values($app->getIdentity()->getAuthorisedViewLevels());
        $language ??= $app->getLanguage()->getTag();
        self::$tags ??= new TagHierarchyRepository($db, new TagHierarchyResolver());
        self::$taxonomies ??= new CategoryTaxonomyResolver($db);
        $resolved = self::$tags->pathForId($tagId, $viewLevels, $language);

        if ($resolved === null) {
            return '';
        }

        $scope = self::$taxonomies->resolve($categoryId, $viewLevels, $language);
        $allowed = array_fill_keys(array_map(static fn (TagNode $node): int => $node->id, $scope['nodes']), true);
        $rootIds = array_fill_keys(array_map(static fn (TagNode $node): int => $node->id, $scope['roots']), true);
        $start = null;

        foreach ($resolved->path as $index => $node) {
            if (isset($rootIds[$node->id])) {
                $start = $index;
                break;
            }
        }

        if ($start === null) {
            return '';
        }

        $rootId = $resolved->path[$start]->id;
        $path = [];

        foreach (array_slice($resolved->path, $start) as $node) {
            if (!isset($allowed[$node->id])) {
                return '';
            }

            $path[] = new TagNode(
                $node->id,
                $node->alias,
                $node->parentId,
                $node->lft,
                $node->rgt,
                $rootId,
                $node->title,
            );
        }

        return self::get($categoryId, new FacetExpression([new FacetGroup([new TagPath($path)])]), $itemId, $language);
    }

    /**
     * Templates pass resolved domain objects, never concatenate aliases. The
     * component router remains responsible for menu selection and SEF output.
     */
    public static function get(
        int $categoryId,
        FacetExpression|TagExpression $expression,
        ?int $itemId = null,
        ?string $language = null,
    ): string
    {
        if (!RoutingFeatures::fromPlugin()->facetedCategories) {
            $query = ['option' => 'com_content', 'view' => 'category', 'id' => $categoryId];

            if ($itemId !== null) {
                $query['Itemid'] = $itemId;
            }

            if ($language !== null && $language !== '*') {
                $query['lang'] = $language;
            }

            return Route::_($query);
        }

        $serializer = new SerializedExpression();
        $public = $expression instanceof FacetExpression ? $expression->tags() : $expression;
        $canonical = $expression->expression();
        $query = [
            'option' => 'com_content',
            'view' => 'category',
            'id' => $categoryId,
            SerializedExpression::REQUEST_KEY => $serializer->encode(
                $categoryId,
                $public,
                (new TagExpressionNormalizer())->normalize($public),
                $canonical,
            ),
        ];

        if ($itemId !== null) {
            $query['Itemid'] = $itemId;
        }

        if ($language !== null && $language !== '*') {
            $query['lang'] = $language;
        }

        return Route::_($query);
    }
}
