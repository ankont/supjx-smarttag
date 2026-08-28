<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Tests;

use PHPUnit\Framework\TestCase;
use SuperSoft\Plugin\System\TagRouting\Domain\TagExpression;
use SuperSoft\Plugin\System\TagRouting\Domain\TagExpressionNormalizer;
use SuperSoft\Plugin\System\TagRouting\Domain\TagGroup;
use SuperSoft\Plugin\System\TagRouting\Domain\TagNode;

final class TagExpressionNormalizerTest extends TestCase
{
    public function testAncestorOrDescendantKeepsAncestorInsideOr(): void
    {
        $ancestor = new TagNode(1, 'bible', 10, 2, 9, 10);
        $descendant = new TagNode(2, 'parables', 1, 3, 4, 10);
        $expression = new TagExpression([new TagGroup([$ancestor, $descendant])]);

        self::assertSame([[1]], (new TagExpressionNormalizer())->normalize($expression)->ids());
    }

    public function testImpliedOrGroupIsRemovedAcrossAnd(): void
    {
        $ancestor = new TagNode(1, 'bible', 10, 2, 9, 10);
        $other = new TagNode(3, 'other', 10, 10, 11, 10);
        $descendant = new TagNode(2, 'parables', 1, 3, 4, 10);
        $expression = new TagExpression([
            new TagGroup([$ancestor, $other]),
            new TagGroup([$descendant]),
        ]);

        self::assertSame([[2]], (new TagExpressionNormalizer())->normalize($expression)->ids());
    }
}
