<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Tests;

use PHPUnit\Framework\TestCase;
use SuperSoft\Plugin\System\TagRouting\Domain\FacetExpressionParser;
use SuperSoft\Plugin\System\TagRouting\Domain\TagExpressionNormalizer;
use SuperSoft\Plugin\System\TagRouting\Domain\TagNode;
use SuperSoft\Plugin\System\TagRouting\Domain\TagPathResolver;

final class TagPathResolverTest extends TestCase
{
    public function testSlashResolvesOneHierarchicalPathToItsLeaf(): void
    {
        $resolver = new TagPathResolver();
        $expression = $resolver->resolve($this->parse('bible/parables'), $this->nodes());

        self::assertNotNull($expression);
        self::assertSame([[3]], $expression->tags()->ids());
        self::assertSame([[3]], (new TagExpressionNormalizer())->normalize($expression->tags())->ids());
        self::assertSame('bible/parables', $expression->expression());
    }

    public function testDuplicateFirstAliasFailsSafely(): void
    {
        self::assertNull((new TagPathResolver())->resolve($this->parse('parables'), $this->nodes()));
    }

    public function testMultipleRootsAndOrGroups(): void
    {
        $nodes = [
            new TagNode(11, 'christmas', 10, 2, 3, 10),
            new TagNode(12, 'easter', 10, 4, 5, 10),
            new TagNode(21, 'lower', 20, 12, 13, 20),
            new TagNode(22, 'upper', 20, 14, 15, 20),
        ];

        $expression = (new TagPathResolver())->resolve($this->parse('christmas,easter+lower,upper'), $nodes);

        self::assertNotNull($expression);
        self::assertSame([[11, 12], [21, 22]], $expression->tags()->ids());
        self::assertSame('christmas,easter+lower,upper', $expression->expression());
    }

    public function testLiteralPlusRestoredAfterJoomlaPathDecode(): void
    {
        $nodes = [
            new TagNode(1, 'a2', 10, 2, 3, 10),
            new TagNode(2, 'a3', 10, 4, 5, 10),
        ];

        self::assertSame([[1], [2]], (new TagPathResolver())->resolve($this->parse('a2 a3'), $nodes)?->tags()->ids());
    }

    public function testSameChildAliasIsDisambiguatedByItsExplicitParentPath(): void
    {
        $nodes = [
            new TagNode(1, 'preschool', 10, 2, 3, 10),
            new TagNode(2, 'primary', 10, 4, 9, 10),
            new TagNode(3, 'gymnasium', 2, 5, 6, 10),
            new TagNode(4, 'secondary', 10, 10, 15, 10),
            new TagNode(5, 'gymnasium', 4, 11, 12, 10),
            new TagNode(6, 'math', 20, 20, 21, 20),
        ];

        $expression = (new TagPathResolver())->resolve(
            $this->parse('preschool,primary/gymnasium,secondary/gymnasium+math'),
            $nodes,
        );

        self::assertSame([[1, 3, 5], [6]], $expression?->tags()->ids());
        self::assertSame(
            [[1, 2, 5]],
            (new TagPathResolver())->resolve(
                $this->parse('preschool,primary,secondary/gymnasium'),
                $nodes,
            )?->tags()->ids(),
        );
        self::assertNull((new TagPathResolver())->resolve($this->parse('gymnasium'), $nodes));
    }

    private function parse(string $expression): \SuperSoft\Plugin\System\TagRouting\Domain\ParsedFacetExpression
    {
        $parsed = (new FacetExpressionParser())->parse($expression);
        self::assertNotNull($parsed);

        return $parsed;
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
