<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'SuperSoft\\Plugin\\System\\TagRouting\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $path = dirname(__DIR__) . '/package/plugins/system/tagrouting/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

    if (is_file($path)) {
        require $path;
    }
});

use SuperSoft\Plugin\System\TagRouting\Domain\SerializedExpression;
use SuperSoft\Plugin\System\TagRouting\Domain\FacetExpressionParser;
use SuperSoft\Plugin\System\TagRouting\Domain\TagExpressionNormalizer;
use SuperSoft\Plugin\System\TagRouting\Domain\TagNode;
use SuperSoft\Plugin\System\TagRouting\Domain\TagPathResolver;
use SuperSoft\Plugin\System\TagRouting\Menu\LogicalFilterState;
use SuperSoft\Plugin\System\TagRouting\Routing\FacetDelimiter;
use SuperSoft\Plugin\System\TagRouting\Routing\StandaloneTagQueryCleaner;
use SuperSoft\Plugin\System\TagRouting\Configuration\RoutingFeatures;
use SuperSoft\Plugin\System\TagRouting\Tag\TagHierarchyNode;
use SuperSoft\Plugin\System\TagRouting\Tag\TagHierarchyResolver;
use SuperSoft\Plugin\System\TagRouting\Tag\StandaloneMenuContext;
use SuperSoft\Plugin\System\TagRouting\Tag\StandaloneMenuSelector;

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    ++$assertions;

    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$nodes = [
    new TagNode(2, 'bible', 1, 2, 7, 1),
    new TagNode(3, 'parables', 2, 3, 4, 1),
    new TagNode(6, 'teaching', 5, 9, 14, 5),
    new TagNode(7, 'parables', 6, 10, 11, 5),
    new TagNode(11, 'a2', 10, 20, 21, 10),
    new TagNode(12, 'a3', 10, 22, 23, 10),
    new TagNode(21, 'math', 20, 30, 31, 20),
    new TagNode(22, 'physics', 20, 32, 33, 20),
];

$resolver = new TagPathResolver();
$parser = new FacetExpressionParser();
$limits = new \SuperSoft\Plugin\System\TagRouting\Configuration\FacetLimits();
$assert($limits->allows(implode('+', array_fill(0, 10, 'a'))), 'Ten AND groups must be allowed.');
$assert(!$limits->allows(implode('+', array_fill(0, 11, 'a'))), 'Eleven AND groups must be rejected.');
$assert($limits->allows(implode(',', array_fill(0, 10, 'a'))), 'Ten OR options must be allowed.');
$assert(!$limits->allows(implode(',', array_fill(0, 11, 'a'))), 'Eleven OR options must be rejected.');
$assert($limits->allows(implode(',', array_fill(0, 10, 'a')) . '+' . implode(',', array_fill(0, 5, 'b'))), 'Fifteen total options must be allowed.');
$assert(!$limits->allows(implode(',', array_fill(0, 10, 'a')) . '+' . implode(',', array_fill(0, 6, 'b'))), 'Sixteen total options must be rejected.');
$single = new \SuperSoft\Plugin\System\TagRouting\Configuration\FacetLimits(1, 1, 1, 9);
$assert($single->allows('root/leaf'), 'A hierarchical path must count as one option.');
$assert(!$single->allows('root/leaff'), 'Expression byte limit was ignored.');
$utf8 = new \SuperSoft\Plugin\System\TagRouting\Configuration\FacetLimits(1, 1, 1, 4);
$assert($utf8->allows('αβ') && !$utf8->allows('αβγ'), 'UTF-8 length must count bytes.');
$custom = \SuperSoft\Plugin\System\TagRouting\Configuration\FacetLimits::fromArray(['facet_max_groups' => 12, 'facet_max_or_options' => 12, 'facet_max_options' => 24, 'facet_max_bytes' => 1500]);
$assert($custom->allows(implode('+', array_fill(0, 12, 'a'))), 'Configured limits were not applied.');
$assert(!$custom->allows(implode('+', array_fill(0, 13, 'a'))), 'Configured AND maximum was ignored.');
$clamped = new \SuperSoft\Plugin\System\TagRouting\Configuration\FacetLimits(999, 999, 999, 9999);
$assert([$clamped->maxGroups, $clamped->maxOrOptions, $clamped->maxOptions, $clamped->maxBytes] === [16, 32, 512, 2048], 'Hard ceilings were ignored.');
$minimum = new \SuperSoft\Plugin\System\TagRouting\Configuration\FacetLimits(0, -1, 0, 0);
$assert([$minimum->maxGroups, $minimum->maxOrOptions, $minimum->maxOptions, $minimum->maxBytes] === [1, 1, 1, 1], 'Zero or negative values disabled protection.');
$assert(!$limits->allows('a,,b') && !$limits->allows('a+') && !$limits->allows(''), 'Malformed expressions were allowed.');
$nestedSyntax = $parser->parse('bible/parables');
$nested = $nestedSyntax === null ? null : $resolver->resolve($nestedSyntax, $nodes);
$assert($nested?->tags()->ids() === [[3]], 'Hierarchical path did not resolve to its leaf.');
$assert($nested?->expression() === 'bible/parables', 'Public hierarchy was shortened.');
$assert((new TagExpressionNormalizer())->normalize($nested->tags())->ids() === [[3]], 'Leaf expression changed during normalization.');
$ambiguous = $parser->parse('parables');
$assert($ambiguous !== null && $resolver->resolve($ambiguous, $nodes) === null, 'Ambiguous first alias was guessed.');

$booleanSyntax = $parser->parse('a2,a3+math,physics');
$boolean = $booleanSyntax === null ? null : $resolver->resolve($booleanSyntax, $nodes);
$assert($boolean?->tags()->ids() === [[11, 12], [21, 22]], 'OR/AND groups did not resolve.');
$decodedPlus = $parser->parse('a2 a3');
$assert($decodedPlus !== null && $resolver->resolve($decodedPlus, $nodes)?->tags()->ids() === [[11], [12]], 'Literal plus restoration failed.');
$assert($parser->parse('(a2,a3)+math') === null, 'Parentheses were unexpectedly accepted.');
$assert($parser->parse('a2,,a3') === null, 'Malformed OR expression was accepted.');

$branchNodes = [
    new TagNode(31, 'preschool', 30, 40, 41, 30),
    new TagNode(32, 'primary', 30, 42, 47, 30),
    new TagNode(33, 'gymnasium', 32, 43, 44, 30),
    new TagNode(34, 'secondary', 30, 48, 53, 30),
    new TagNode(35, 'gymnasium', 34, 49, 50, 30),
];
$branches = $parser->parse('preschool,primary/gymnasium,secondary/gymnasium');
$assert(
    $branches !== null && $resolver->resolve($branches, $branchNodes)?->tags()->ids() === [[31, 33, 35]],
    'Explicit parent paths did not disambiguate duplicate child aliases.',
);
$expectedExample = $parser->parse('preschool,primary,secondary/gymnasium');
$assert(
    $expectedExample !== null && $resolver->resolve($expectedExample, $branchNodes)?->tags()->ids() === [[31, 32, 35]],
    'Branch refinement changed unrelated OR alternatives.',
);
$bareChild = $parser->parse('gymnasium');
$assert($bareChild !== null && $resolver->resolve($bareChild, $branchNodes) === null, 'Ambiguous child alias was guessed.');

$serializer = new SerializedExpression();
$nestedTags = $nested->tags();
$decoded = $serializer->decode($serializer->encode(9, $nestedTags, (new TagExpressionNormalizer())->normalize($nestedTags), $nested->expression()));
$assert($decoded['category'] === 9 && $decoded['groups'] === [[3]], 'Expression round-trip failed.');
$assert($decoded['expression'] === 'bible/parables', 'Canonical expression was lost.');
$assert($decoded['resolved'][0][0]['root'] === 1, 'Taxonomy root metadata was lost.');
$assert($serializer->decode('{"v":2,"category":9,"groups":[[-1]]}') === null, 'Malformed state was accepted.');

$states = new LogicalFilterState($serializer);
$assert(
    $states->from(['filter_tag' => [42, 17], 'types' => [2, 1]])
        === $states->from(['filter_tag' => [17, 42], 'types' => [1, 2]]),
    'Logical menu state depended on ordering.',
);
$assert($states->from(['tags' => [42, 17], 'match_all' => 1])['groups'] === [[17], [42]], 'AND menu semantics failed.');
$assert(
    (new LogicalFilterState($serializer, false))->from([SerializedExpression::REQUEST_KEY => $serializer->encode(9, $nestedTags, $nestedTags, 'bible/parables')])['groups'] === [],
    'Disabled faceting leaked serialized state into cleanup.',
);

$delimiter = new FacetDelimiter();
$christmasNodes = [new TagNode(42, 'christmas', 10, 40, 41, 10)];
$resolvedStates = [];

foreach ([['!', 'christmas'], ['!filter', 'christmas'], ['!tag', 'christmas']] as $input) {
    $match = $delimiter->extract($input);
    $assert($match !== null, 'Accepted delimiter form was rejected.');
    $syntax = $parser->parse(implode('/', $match['segments']));
    $resolvedStates[] = $syntax === null ? null : $resolver->resolve($syntax, $christmasNodes)?->tags()->ids();
    $assert($delimiter->canonicalSegments($match['segments']) === ['!', 'christmas'], 'Delimiter did not build canonically.');
}

$assert($resolvedStates === [[[42]], [[42]], [[42]]], 'Delimiter aliases resolved to different filter states.');
$assert($delimiter->extract(['christmas']) === null, 'Bare tag route was accepted.');
$assert($delimiter->extract(['filter', 'christmas']) === null, 'Bare filter alias was accepted.');
$assert($delimiter->extract(['tag', 'christmas']) === null, 'Bare tag alias was accepted.');
$assert($delimiter->extract(['!']) === null, 'Delimiter without a filter was accepted.');
$assert($delimiter->extract(['unresolved', '!', 'christmas']) === null, 'Delimiter after an unresolved real route was accepted.');

$standaloneQuery = (new StandaloneTagQueryCleaner())->clean([
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
]);
$assert($standaloneQuery === ['lang' => 'el-GR', 'custom' => 'preserved'], 'Standalone route leaked category or preview context.');

$defaults = RoutingFeatures::fromArray([]);
$assert($defaults->canonicalCleanup && !$defaults->hierarchicalTags && $defaults->facetedCategories, 'Upgrade defaults changed existing behavior.');
$assert($defaults->acceptedMarkers === ['!', '!tag', '!filter'], 'Default accepted markers changed.');
$assert($defaults->canonicalMarker === '!', 'Default canonical marker changed.');
$assert($defaults->breadcrumbMarker === '#', 'Default breadcrumb marker changed.');
$assert(
    RoutingFeatures::fromArray(['show_breadcrumb_marker' => 0, 'breadcrumb_marker' => '#'])->breadcrumbMarker === '',
    'Disabled breadcrumb marker was still rendered.',
);

for ($mask = 0; $mask < 8; ++$mask) {
    $features = RoutingFeatures::fromArray([
        'enable_cleanup' => (int) (bool) ($mask & 1),
        'enable_hierarchical' => (int) (bool) ($mask & 2),
        'enable_faceted' => (int) (bool) ($mask & 4),
    ]);
    $assert($features->canonicalCleanup === (bool) ($mask & 1), 'Cleanup toggle leaked into another feature.');
    $assert($features->hierarchicalTags === (bool) ($mask & 2), 'Hierarchical toggle leaked into another feature.');
    $assert($features->facetedCategories === (bool) ($mask & 4), 'Faceted toggle leaked into another feature.');
    $assert($features->needsContentRouter() === (bool) ($mask & 5), 'Content router activation plan is wrong.');
    $assert($features->needsSiteRules() === (bool) ($mask & 3), 'Site rule activation plan is wrong.');
}

$customMarkers = RoutingFeatures::fromArray([
    'accepted_markers' => ['!tag'],
    'enable_custom_marker' => 1,
    'custom_marker' => '/!Facet/',
    'canonical_marker' => '__custom__',
]);
$assert($customMarkers->acceptedMarkers === ['!tag', '!facet'], 'Custom accepted marker was not normalized.');
$assert($customMarkers->canonicalMarker === '!facet', 'Custom canonical marker was not selected.');
$markerFallback = RoutingFeatures::fromArray(['accepted_markers' => ['!filter'], 'canonical_marker' => '!']);
$assert($markerFallback->canonicalMarker === '!filter', 'Disabled canonical marker did not fall back safely.');
$configuredDelimiter = new FacetDelimiter(['!tag', '!facet'], '!facet');
$assert($configuredDelimiter->extract(['!', 'christmas']) === null, 'Disabled marker remained accepted.');
$assert($configuredDelimiter->extract(['!tag', 'christmas'])['canonical'] === false, 'Alias marker became canonical.');
$assert($configuredDelimiter->canonicalSegments(['christmas']) === ['!facet', 'christmas'], 'Configured canonical marker was ignored.');

$menuSelector = new StandaloneMenuSelector();
$menuContexts = [
    new StandaloneMenuContext(10, 'tags', '*', true),
    new StandaloneMenuContext(20, 'menu-a/menu-b/tags', 'el-GR', false),
];
$assert($menuSelector->best($menuContexts, 'el-GR')?->itemId === 20, 'Current-language Tag Pages item was not preferred.');
$assert(
    $menuSelector->match(['menu-a', 'menu-b', 'tags', 'age-group'], $menuContexts, 'el-GR')?->itemId === 20,
    'Full nested Tag Pages route was not treated as the namespace boundary.',
);
// Root-marker ownership and canonical standalone routes must stay consistent.
foreach ([true, false] as $canonicalMenu) {
    $bound = new StandaloneMenuContext(30, 'tags', '*', $canonicalMenu);
    $assert($bound->handlesRootMarker(), 'Existing menu defaults released root marker ownership.');
    $assert($bound->usesMenuRoute() === $canonicalMenu, 'Bound marker changed canonical preference.');
    $independent = new StandaloneMenuContext(30, 'tags', '*', $canonicalMenu, false);
    $assert(!$independent->handlesRootMarker(), 'Independent marker was claimed by standalone context.');
    $assert($independent->usesMenuRoute(), 'Standalone links collided with independent filter URLs.');
    $empty = new StandaloneMenuContext(30, '', '*', $canonicalMenu, false);
    $assert($empty->handlesRootMarker() && !$empty->usesMenuRoute(), 'Empty menu route lost standalone fallback.');
}
$independentContexts = [new StandaloneMenuContext(10, 'tags', '*', true), new StandaloneMenuContext(20, 'el/tags', 'el-GR', true, false)];
$assert(!$menuSelector->best($independentContexts, 'el-GR')->handlesRootMarker(), 'Language-specific marker independence was ignored.');
$assert($menuSelector->best($independentContexts, 'en-GB')->handlesRootMarker(), 'Marker independence leaked across languages.');
$assert($menuSelector->match(['el', 'tags', 'age-group'], $independentContexts, 'el-GR')?->itemId === 20, 'Independent root marker disabled the standalone menu namespace.');
$strictNodes = [
    new TagNode(201, 'root', 1, 1, 8, 201),
    new TagNode(202, 'middle', 201, 2, 7, 201),
    new TagNode(203, 'leaf', 202, 3, 4, 201),
];
$strictPath = $parser->parse('root/middle/leaf');
$skippedPath = $parser->parse('root/leaf');
$assert($strictPath !== null && $resolver->resolve($strictPath, $strictNodes)?->tags()->ids() === [[203]], 'Exact parent-child path failed.');
$assert($skippedPath !== null && $resolver->resolve($skippedPath, $strictNodes) === null, 'Skipped hierarchy level was accepted.');

$tagTree = [
    new TagHierarchyNode(102, 'theme', 1, 2, 9, '*'),
    new TagHierarchyNode(103, 'bible', 102, 3, 8, '*'),
    new TagHierarchyNode(104, 'parables', 103, 4, 5, '*'),
    new TagHierarchyNode(105, 'other', 1, 10, 17, '*'),
    new TagHierarchyNode(106, 'something', 105, 11, 16, '*'),
    new TagHierarchyNode(107, 'parables', 106, 12, 13, '*'),
];
$tagResolver = new TagHierarchyResolver();
$assert($tagResolver->resolve(['theme', 'bible', 'parables'], $tagTree, 'en-GB')?->id() === 104, 'Exact hierarchical tag did not resolve.');
$assert($tagResolver->resolve(['other', 'something', 'parables'], $tagTree, 'en-GB')?->id() === 107, 'Duplicate alias branch was guessed incorrectly.');
$assert($tagResolver->resolve(['theme', 'bible', 'does-not-exist'], $tagTree, 'en-GB') === null, 'Invalid tag path did not fail.');
$assert($tagResolver->resolve(['parables'], $tagTree, 'en-GB') === null, 'Non-root first alias was accepted.');

echo "OK ({$assertions} assertions)\n";
