<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Tests;

use PHPUnit\Framework\TestCase;
use SuperSoft\Plugin\System\TagRouting\Tag\TagHierarchyNode;
use SuperSoft\Plugin\System\TagRouting\Tag\TagHierarchyResolver;

final class TagHierarchyResolverTest extends TestCase
{
    public function testFullParentChildPathSelectsTheExactDuplicateAlias(): void
    {
        $resolver = new TagHierarchyResolver();

        self::assertSame(
            4,
            $resolver->resolve(['theme', 'bible', 'parables'], $this->nodes(), 'en-GB')?->id(),
        );
        self::assertSame(
            7,
            $resolver->resolve(['other', 'something', 'parables'], $this->nodes(), 'en-GB')?->id(),
        );
    }

    public function testInvalidOrNonRootedPathFailsWithoutFallback(): void
    {
        $resolver = new TagHierarchyResolver();

        self::assertNull($resolver->resolve(['theme', 'bible', 'does-not-exist'], $this->nodes(), 'en-GB'));
        self::assertNull($resolver->resolve(['parables'], $this->nodes(), 'en-GB'));
        self::assertNull($resolver->resolve(['bible', 'parables'], $this->nodes(), 'en-GB'));
    }

    public function testCurrentLanguageWinsOverAllLanguageSibling(): void
    {
        $nodes = [
            new TagHierarchyNode(10, 'series', 1, 2, 5, '*'),
            new TagHierarchyNode(11, 'series', 1, 6, 9, 'el-GR'),
        ];

        self::assertSame(11, (new TagHierarchyResolver())->resolve(['series'], $nodes, 'el-GR')?->id());
        self::assertSame(10, (new TagHierarchyResolver())->resolve(['series'], $nodes, 'en-GB')?->id());
    }

    /** @return list<TagHierarchyNode> */
    private function nodes(): array
    {
        return [
            new TagHierarchyNode(2, 'theme', 1, 2, 9, '*'),
            new TagHierarchyNode(3, 'bible', 2, 3, 8, '*'),
            new TagHierarchyNode(4, 'parables', 3, 4, 5, '*'),
            new TagHierarchyNode(5, 'other', 1, 10, 17, '*'),
            new TagHierarchyNode(6, 'something', 5, 11, 16, '*'),
            new TagHierarchyNode(7, 'parables', 6, 12, 13, '*'),
        ];
    }
}
