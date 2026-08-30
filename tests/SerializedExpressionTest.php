<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Tests;

use PHPUnit\Framework\TestCase;
use SuperSoft\Plugin\System\TagRouting\Domain\SerializedExpression;
use SuperSoft\Plugin\System\TagRouting\Domain\TagExpression;
use SuperSoft\Plugin\System\TagRouting\Domain\TagGroup;
use SuperSoft\Plugin\System\TagRouting\Domain\TagNode;

final class SerializedExpressionTest extends TestCase
{
    public function testRoundTripPreservesPublicPathAndRootMetadata(): void
    {
        $tag = new TagNode(42, 'christmas', 10, 2, 3, 10);
        $expression = new TagExpression([new TagGroup([$tag])]);
        $serializer = new SerializedExpression();
        $decoded = $serializer->decode($serializer->encode(7, $expression, $expression));

        self::assertSame(7, $decoded['category']);
        self::assertSame('christmas', $decoded['expression']);
        self::assertSame(['christmas'], $decoded['segments']);
        self::assertSame([[42]], $decoded['groups']);
        self::assertSame(10, $decoded['resolved'][0][0]['root']);
    }

    public function testMalformedClientStateIsRejected(): void
    {
        self::assertNull((new SerializedExpression())->decode('{"v":2,"category":7,"groups":[[-1]]}'));
    }
}
