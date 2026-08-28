<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Routing;

use Joomla\CMS\Factory;
use SuperSoft\Plugin\System\TagRouting\Domain\SerializedExpression;

final class FacetedRequest
{
    /**
     * Gives template overrides read-only access to the resolved expression and
     * per-tag taxonomy root metadata without exposing router implementation.
     *
     * @return array{category:int,segments:list<string>,groups:list<list<int>>,resolved:list<list<array{id:int,alias:string,root:int>>>}|null
     */
    public static function current(): ?array
    {
        $value = Factory::getApplication()->getInput()->getString(SerializedExpression::REQUEST_KEY);

        return (new SerializedExpression())->decode($value);
    }
}
