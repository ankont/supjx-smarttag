<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Tests;

use PHPUnit\Framework\TestCase;
use SuperSoft\Plugin\System\TagRouting\Domain\TagExpressionNormalizer;
use SuperSoft\Plugin\System\TagRouting\Domain\TagNode;
use SuperSoft\Plugin\System\TagRouting\Domain\TagPathResolver;

final class TagPathResolverTest extends TestCase
{
    public function testNestedPathUsesPrecedingBranchAndNormalizesAncestor(): void
    {
        $resolver = new TagPathResolver();
        $expression = $resolver->resolve(['bible', 'parables'], $this->nodes());

        self::assertNotNull($expression);
        self::assertSame([[2], [3]], $expression->ids());
        self::assertSame([[3]], (new TagExpressionNormalizer())->normalize($expression)->ids());
        self::assertSame(['bible', 'parables'], $expression->segments());
    }

    public function testDuplicateFirstAliasFailsSafely(): void
    {
        self::assertNull((new TagPathResolver())->resolve(['parables'], $this->nodes()));
    }

    public function testMultipleRootsAndOrGroups(): void
    {
        $nodes = [
            new TagNode(11, 'christmas', 10, 2, 3, 10),
            new TagNode(12, 'easter', 10, 4, 5, 10),
            new TagNode(21, 'lower', 20, 12, 13, 20),
            new TagNode(22, 'upper', 20, 14, 15, 20),
        ];

        $expression = (new TagPathResolver())->resolve(['christmas+easter', 'lower+upper'], $nodes);

        self::assertNotNull($expression);
        self::assertSame([[11, 12], [21, 22]], $expression->ids());
        self::assertSame(['christmas+easter', 'lower+upper'], $expression->segments());
    }

    public function testLiteralPlusRestoredAfterJoomlaPathDecode(): void
    {
        $nodes = [
            new TagNode(1, 'a2', 10, 2, 3, 10),
            new TagNode(2, 'a3', 10, 4, 5, 10),
        ];

        self::assertSame([[1, 2]], (new TagPathResolver())->resolve(['a2 a3'], $nodes)?->ids());
    }

    /** @return list<TagNode> */
    private function nodes(): array
    {
        return [
            new TagNode(2, 'bible', 1, 2, 7, 1),
            new TagNode(3, 'parables', 2, 3, 4, 1),
            new TagNode(4, 'miracles', 2, 5, 6, 1),
            new TagNode(6, 'teaching', 5, 9, 14, 5),
            new TagNode(7, 'parables', 6, 10, 11, 5),
        ];
    }
}
