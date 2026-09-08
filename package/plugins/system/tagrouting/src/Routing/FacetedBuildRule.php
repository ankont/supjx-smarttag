<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Routing;

use Joomla\CMS\Component\Router\Rules\RulesInterface;
use SuperSoft\Plugin\System\TagRouting\Domain\SerializedExpression;

final readonly class FacetedBuildRule implements RulesInterface
{
    public function __construct(
        private SerializedExpression $serializer,
        private FacetDelimiter $delimiter,
    ) {
    }

    public function preprocess(&$query)
    {
    }

    public function parse(&$segments, &$vars)
    {
    }

    public function build(&$query, &$segments)
    {
        $state = $this->serializer->decode($query[SerializedExpression::REQUEST_KEY] ?? null);

        if ($state === null) {
            return;
        }

        // StandardRules may already have consumed these keys. If they remain,
        // however, they must still describe the serialized category route.
        if (isset($query['view']) && $query['view'] !== 'category') {
            return;
        }

        if (isset($query['id']) && (int) $query['id'] !== $state['category']) {
            return;
        }

        foreach ($this->delimiter->canonicalSegments(explode('/', $state['expression'])) as $segment) {
            $segments[] = $segment;
        }

        unset($query[SerializedExpression::REQUEST_KEY]);
    }
}
