<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Routing;

use Joomla\CMS\Router\Route;
use SuperSoft\Plugin\System\TagRouting\Domain\FacetExpression;
use SuperSoft\Plugin\System\TagRouting\Domain\SerializedExpression;
use SuperSoft\Plugin\System\TagRouting\Domain\TagExpression;
use SuperSoft\Plugin\System\TagRouting\Domain\TagExpressionNormalizer;

final class FacetedRoute
{
    /**
     * Templates pass resolved domain objects, never concatenate aliases. The
     * component router remains responsible for menu selection and SEF output.
     */
    public static function get(int $categoryId, FacetExpression|TagExpression $expression, ?int $itemId = null): string
    {
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

        return Route::_($query);
    }
}
