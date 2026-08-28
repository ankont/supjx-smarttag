<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SuperSoft\Plugin\System\TagRouting\Domain\TagNode;
use SuperSoft\Plugin\System\TagRouting\Domain\TagPathResolver;
use SuperSoft\Plugin\System\TagRouting\Routing\FacetDelimiter;

final class FacetDelimiterTest extends TestCase
{
    /**
     * @return iterable<string, array{list<string>,bool}>
     */
    public static function acceptedForms(): iterable
    {
        yield 'canonical' => [['!', 'christmas'], true];
        yield 'filter alias' => [['!filter', 'christmas'], false];
        yield 'tag alias' => [['!tag', 'christmas'], false];
    }

    /** @param list<string> $input */
    #[DataProvider('acceptedForms')]
    public function testAcceptedFormsResolveIdenticallyAndBuildCanonical(array $input, bool $canonical): void
    {
        $delimiter = new FacetDelimiter();
        $match = $delimiter->extract($input);
        $nodes = [new TagNode(42, 'christmas', 10, 2, 3, 10)];

        self::assertNotNull($match);
        self::assertSame($canonical, $match['canonical']);
        self::assertSame([[42]], (new TagPathResolver())->resolve($match['segments'], $nodes)?->ids());
        self::assertSame(['!', 'christmas'], $delimiter->canonicalSegments($match['segments']));
    }

    public function testBareAndIncompleteFormsAreRejected(): void
    {
        $delimiter = new FacetDelimiter();

        self::assertNull($delimiter->extract(['christmas']));
        self::assertNull($delimiter->extract(['filter', 'christmas']));
        self::assertNull($delimiter->extract(['tag', 'christmas']));
        self::assertNull($delimiter->extract(['!']));
        self::assertNull($delimiter->extract(['unresolved-real-route', '!', 'christmas']));
    }
}
