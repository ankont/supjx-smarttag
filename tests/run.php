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
use SuperSoft\Plugin\System\TagRouting\Domain\TagExpressionNormalizer;
use SuperSoft\Plugin\System\TagRouting\Domain\TagNode;
use SuperSoft\Plugin\System\TagRouting\Domain\TagPathResolver;
use SuperSoft\Plugin\System\TagRouting\Menu\LogicalFilterState;
use SuperSoft\Plugin\System\TagRouting\Routing\FacetDelimiter;

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
$nested = $resolver->resolve(['bible', 'parables'], $nodes);
$assert($nested?->ids() === [[2], [3]], 'Contextual nested path did not resolve.');
$assert((new TagExpressionNormalizer())->normalize($nested)->ids() === [[3]], 'Ancestor was not normalized.');
$assert($nested->segments() === ['bible', 'parables'], 'Public hierarchy was shortened.');
$assert($resolver->resolve(['parables'], $nodes) === null, 'Ambiguous first alias was guessed.');

$boolean = $resolver->resolve(['a2+a3', 'math+physics'], $nodes);
$assert($boolean?->ids() === [[11, 12], [21, 22]], 'OR/AND groups did not resolve.');
$assert($resolver->resolve(['a2 a3'], $nodes)?->ids() === [[11, 12]], 'Literal plus restoration failed.');

$serializer = new SerializedExpression();
$decoded = $serializer->decode($serializer->encode(9, $nested, (new TagExpressionNormalizer())->normalize($nested)));
$assert($decoded['category'] === 9 && $decoded['groups'] === [[3]], 'Expression round-trip failed.');
$assert($decoded['resolved'][0][0]['root'] === 1, 'Taxonomy root metadata was lost.');
$assert($serializer->decode('{"v":1,"category":9,"groups":[[-1]]}') === null, 'Malformed state was accepted.');

$states = new LogicalFilterState($serializer);
$assert(
    $states->from(['filter_tag' => [42, 17], 'types' => [2, 1]])
        === $states->from(['filter_tag' => [17, 42], 'types' => [1, 2]]),
    'Logical menu state depended on ordering.',
);
$assert($states->from(['tags' => [42, 17], 'match_all' => 1])['groups'] === [[17], [42]], 'AND menu semantics failed.');

$delimiter = new FacetDelimiter();
$christmasNodes = [new TagNode(42, 'christmas', 10, 40, 41, 10)];
$resolvedStates = [];

foreach ([['!', 'christmas'], ['!filter', 'christmas'], ['!tag', 'christmas']] as $input) {
    $match = $delimiter->extract($input);
    $assert($match !== null, 'Accepted delimiter form was rejected.');
    $resolvedStates[] = $resolver->resolve($match['segments'], $christmasNodes)?->ids();
    $assert($delimiter->canonicalSegments($match['segments']) === ['!', 'christmas'], 'Delimiter did not build canonically.');
}

$assert($resolvedStates === [[[42]], [[42]], [[42]]], 'Delimiter aliases resolved to different filter states.');
$assert($delimiter->extract(['christmas']) === null, 'Bare tag route was accepted.');
$assert($delimiter->extract(['filter', 'christmas']) === null, 'Bare filter alias was accepted.');
$assert($delimiter->extract(['tag', 'christmas']) === null, 'Bare tag alias was accepted.');
$assert($delimiter->extract(['!']) === null, 'Delimiter without a filter was accepted.');
$assert($delimiter->extract(['unresolved', '!', 'christmas']) === null, 'Delimiter after an unresolved real route was accepted.');

echo "OK ({$assertions} assertions)\n";
