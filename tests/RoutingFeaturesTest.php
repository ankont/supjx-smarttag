<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Tests;

use PHPUnit\Framework\TestCase;
use SuperSoft\Plugin\System\TagRouting\Configuration\RoutingFeatures;

final class RoutingFeaturesTest extends TestCase
{
    public function testUpgradeDefaultsPreserveExistingFeaturesAndKeepNewNamespaceOptIn(): void
    {
        $features = RoutingFeatures::fromArray([]);

        self::assertTrue($features->canonicalCleanup);
        self::assertFalse($features->hierarchicalTags);
        self::assertTrue($features->facetedCategories);
        self::assertSame('tags', $features->tagPrefix);
    }

    public function testAllEightFeatureCombinationsRemainIndependent(): void
    {
        for ($mask = 0; $mask < 8; ++$mask) {
            $cleanup = (bool) ($mask & 1);
            $hierarchical = (bool) ($mask & 2);
            $faceted = (bool) ($mask & 4);
            $features = RoutingFeatures::fromArray([
                'enable_cleanup' => (int) $cleanup,
                'enable_hierarchical' => (int) $hierarchical,
                'enable_faceted' => (int) $faceted,
            ]);

            self::assertSame($cleanup, $features->canonicalCleanup);
            self::assertSame($hierarchical, $features->hierarchicalTags);
            self::assertSame($faceted, $features->facetedCategories);
            self::assertSame($cleanup || $faceted, $features->needsContentRouter());
            self::assertSame($cleanup || $hierarchical, $features->needsSiteRules());
        }
    }

    public function testPrefixIsOneSafeSegment(): void
    {
        self::assertSame('topics', RoutingFeatures::fromArray(['tag_url_prefix' => '/Topics/'])->tagPrefix);
        self::assertSame('tags', RoutingFeatures::fromArray(['tag_url_prefix' => '../unsafe'])->tagPrefix);
        self::assertSame('tags', RoutingFeatures::fromArray(['tag_url_prefix' => 'two/segments'])->tagPrefix);
    }
}
