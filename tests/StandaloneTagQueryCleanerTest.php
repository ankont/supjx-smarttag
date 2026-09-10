<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Tests;

use PHPUnit\Framework\TestCase;
use SuperSoft\Plugin\System\TagRouting\Routing\StandaloneTagQueryCleaner;

final class StandaloneTagQueryCleanerTest extends TestCase
{
    public function testCategoryAndPreviewContextCannotLeakIntoStandaloneTagRoutes(): void
    {
        $query = [
            'option' => 'com_tags',
            'view' => 'tag',
            'id' => [42],
            'Itemid' => 123,
            'layout' => 'default',
            'catid' => 9,
            'tmpl' => 'component',
            'tag_id' => 42,
            'tag_preview' => 1,
            'lang' => 'el-GR',
            'custom' => 'preserved',
        ];

        self::assertSame(
            ['lang' => 'el-GR', 'custom' => 'preserved'],
            (new StandaloneTagQueryCleaner())->clean($query),
        );
    }
}
