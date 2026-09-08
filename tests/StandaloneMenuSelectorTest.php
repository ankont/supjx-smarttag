<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Tests;

use PHPUnit\Framework\TestCase;
use SuperSoft\Plugin\System\TagRouting\Tag\StandaloneMenuContext;
use SuperSoft\Plugin\System\TagRouting\Tag\StandaloneMenuSelector;

final class StandaloneMenuSelectorTest extends TestCase
{
    public function testCurrentLanguageWinsAndAllLanguageIsFallback(): void
    {
        $selector = new StandaloneMenuSelector();
        $contexts = [
            new StandaloneMenuContext(10, 'tags', '*', true),
            new StandaloneMenuContext(20, 'etiketes', 'el-GR', false),
        ];

        self::assertSame(20, $selector->best($contexts, 'el-GR')?->itemId);
        self::assertSame(10, $selector->best($contexts, 'en-GB')?->itemId);
    }

    public function testFullLongestMenuRouteIsAuthoritativeBoundary(): void
    {
        $selector = new StandaloneMenuSelector();
        $contexts = [
            new StandaloneMenuContext(10, 'tags', '*', true),
            new StandaloneMenuContext(20, 'menu-a/menu-b/tags', '*', true),
        ];

        self::assertSame(
            20,
            $selector->match(['menu-a', 'menu-b', 'tags', 'age-group', 'secondary'], $contexts, 'en-GB')?->itemId,
        );
        self::assertNull($selector->match(['menu-a', 'menu-b', 'not-tags', 'secondary'], $contexts, 'en-GB'));
    }
}
