<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SuperSoft\Plugin\System\TagRouting\Domain\FacetExpressionParser;

final class FacetExpressionParserTest extends TestCase
{
    public function testNaturalGrammarSeparatesOrGroupsFromAndGroups(): void
    {
        $parsed = (new FacetExpressionParser())->parse('preschool,primary/gymnasium,secondary/gymnasium+math,physics');

        self::assertNotNull($parsed);
        self::assertSame('preschool,primary/gymnasium,secondary/gymnasium+math,physics', $parsed->canonical);
        self::assertSame([
            [['preschool'], ['primary', 'gymnasium'], ['secondary', 'gymnasium']],
            [['math'], ['physics']],
        ], $parsed->groups);
    }

    public function testJoomlaDecodedPlusIsRestoredAsAnd(): void
    {
        self::assertSame('a2,a3+math', (new FacetExpressionParser())->parse('a2,a3 math')?->canonical);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidExpressions(): iterable
    {
        foreach (['', '+a', 'a+', 'a,,b', 'a//b', '(a,b)+c', 'a+(b,c)'] as $value) {
            yield $value => [$value];
        }
    }

    #[DataProvider('invalidExpressions')]
    public function testMalformedAndParenthesizedExpressionsAreRejected(string $value): void
    {
        self::assertNull((new FacetExpressionParser())->parse($value));
    }
}
