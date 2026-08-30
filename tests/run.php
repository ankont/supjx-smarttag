<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'SuperSoft\\Plugin\\System\\TagRouting\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $path = dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

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
use SuperSoft\Plugin\System\TagRouting\Configuration\RoutingFeatures;
use SuperSoft\Plugin\System\TagRouting\Tag\TagHierarchyNode;
use SuperSoft\Plugin\System\TagRouting\Tag\TagHierarchyResolver;

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

$defaults = RoutingFeatures::fromArray([]);
$assert($defaults->canonicalCleanup && !$defaults->hierarchicalTags && $defaults->facetedCategories, 'Upgrade defaults changed existing behavior.');
$assert($defaults->tagPrefix === 'tags', 'Default tag prefix changed.');

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

$assert(RoutingFeatures::fromArray(['tag_url_prefix' => '/Topics/'])->tagPrefix === 'topics', 'Safe prefix was not normalized.');
$assert(RoutingFeatures::fromArray(['tag_url_prefix' => '../unsafe'])->tagPrefix === 'tags', 'Unsafe prefix was accepted.');

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
