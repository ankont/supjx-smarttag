<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Tests;

use PHPUnit\Framework\TestCase;
use SuperSoft\Plugin\System\TagRouting\Configuration\RoutingFeatures;

final class RoutingFeaturesTest extends TestCase
{
    public function testUpgradeDefaultsPreserveExistingFeaturesAndKeepStandaloneRoutingOptIn(): void
    {
        $features = RoutingFeatures::fromArray([]);

        self::assertTrue($features->canonicalCleanup);
        self::assertFalse($features->hierarchicalTags);
        self::assertTrue($features->facetedCategories);
        self::assertSame(['!', '!tag', '!filter'], $features->acceptedMarkers);
        self::assertSame('!', $features->canonicalMarker);
        self::assertSame('#', $features->breadcrumbMarker);
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

    public function testMarkersAreValidatedAndCanonicalMustBeAccepted(): void
    {
        $custom = RoutingFeatures::fromArray([
            'accepted_markers' => ['!tag'],
            'enable_custom_marker' => 1,
            'custom_marker' => '/!Facet/',
            'canonical_marker' => '__custom__',
        ]);
        self::assertSame(['!tag', '!facet'], $custom->acceptedMarkers);
        self::assertSame('!facet', $custom->canonicalMarker);

        $fallback = RoutingFeatures::fromArray([
            'accepted_markers' => ['!filter'],
            'canonical_marker' => '!',
            'enable_custom_marker' => 1,
            'custom_marker' => '../unsafe',
        ]);
        self::assertSame(['!filter'], $fallback->acceptedMarkers);
        self::assertSame('!filter', $fallback->canonicalMarker);
        self::assertSame(['!'], RoutingFeatures::fromArray(['accepted_markers' => []])->acceptedMarkers);
        self::assertSame('', RoutingFeatures::fromArray(['breadcrumb_marker' => ''])->breadcrumbMarker);
        self::assertSame('', RoutingFeatures::fromArray([
            'show_breadcrumb_marker' => 0,
            'breadcrumb_marker' => '#',
        ])->breadcrumbMarker);
    }
}
