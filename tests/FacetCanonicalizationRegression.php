<?php

declare(strict_types=1);

require __DIR__ . '/run.php';

use SuperSoft\Plugin\System\TagRouting\Configuration\FacetLimits;
use SuperSoft\Plugin\System\TagRouting\Domain\FacetExpressionCanonicalizer;
use SuperSoft\Plugin\System\TagRouting\Domain\FacetExpressionParser;
use SuperSoft\Plugin\System\TagRouting\Domain\TagExpressionNormalizer;
use SuperSoft\Plugin\System\TagRouting\Domain\TagNode;
use SuperSoft\Plugin\System\TagRouting\Domain\TagPathResolver;

$fixture = [
    new TagNode(1, 'root', 0, 1, 10, 1),
    new TagNode(2, 'a', 1, 2, 5, 1),
    new TagNode(3, 'child', 2, 3, 4, 1),
    new TagNode(4, 'b', 1, 6, 9, 1),
    new TagNode(5, 'child', 4, 7, 8, 1),
    new TagNode(6, 'c', 0, 11, 12, 6),
];
$parse = new FacetExpressionParser(new FacetLimits());
$resolve = new TagPathResolver();
$canonicalizer = new FacetExpressionCanonicalizer();
$canonical = static function (string $raw) use ($parse, $resolve, $canonicalizer, $fixture) {
    $syntax = $parse->parse($raw);
    $resolved = $syntax === null ? null : $resolve->resolve($syntax, $fixture);
    return $resolved === null ? null : $canonicalizer->normalize($resolved, $fixture);
};
foreach (['a+b', 'b+a', 'a,a+b', 'a+b+a', 'root/a+b', 'b+root/a+a'] as $raw) {
    $assert($canonical($raw)?->expression() === 'a+b', 'Equivalent form did not canonicalize: ' . $raw);
}
$assert($parse->parse('b+a,a')->canonical === 'a+b', 'Parser structural canonicalization failed.');
$assert($canonical('a,b+a')->expression() === 'a', 'Exact Boolean absorption failed.');
$assert($canonical('a/child+b/child')->expression() === 'a/child+b/child', 'Hierarchy ordering/qualification was lost.');
$assert($canonical('child') === null && $canonical('child/a') === null, 'Ambiguous/reversed path was accepted.');
$assert($canonical('a+a/child')->expression() === 'a+a/child', 'Ancestor was incorrectly implied by its child.');
$assert($canonical('a,a/child')->expression() === 'a,a/child', 'Ancestor OR child was incorrectly simplified.');
$assert($canonical('a,b+c')->expression() !== $canonical('a+b,c')->expression(), 'Different AND/OR expressions were merged.');
$forms = ['a+b', 'b+a', 'a,a+b', 'a+b+a', 'a,b+a', 'a,b+c', 'a+b,c', 'a+a/child', 'a,a/child', 'root/a/child+b/child', 'a,b+a,c'];
$evaluations = 0;
foreach ($forms as $raw) {
    $resolved = $resolve->resolve($parse->parse($raw), $fixture);
    $normalized = $canonical($raw);
    $assert($canonical($normalized->expression())->expression() === $normalized->expression(), 'Normalization is not idempotent.');
    for ($mask = 0; $mask < 64; ++$mask) {
        $tagged = [];
        for ($id = 1; $id <= 6; ++$id) {
            if ($mask & (1 << ($id - 1))) { $tagged[] = $id; }
        }
        $matches = static function ($expression) use ($tagged): bool {
            foreach ($expression->groups as $group) {
                if (array_intersect($tagged, $group->ids()) === []) { return false; }
            }
            return true;
        };
        $before = $matches($resolved->tags());
        $assert($before === $matches($normalized->tags()), 'Canonicalization changed exact-membership results: ' . $raw);
        $assert($before === $matches((new TagExpressionNormalizer())->normalize($resolved->tags())), 'Domain normalization changed exact-membership results: ' . $raw);
        ++$evaluations;
    }
}
$assert(!$parse->parse(implode('+', array_fill(0, 11, 'a'))), 'Duplicate groups bypassed raw limits.');
$assert(!$parse->parse(implode(',', array_fill(0, 11, 'a'))), 'Duplicate options bypassed raw limits.');
$assert(!$parse->parse('a,,b') && !$parse->parse('a++b'), 'Malformed syntax accepted.');
echo "OK ({$evaluations} exhaustive exact-membership evaluations; {$assertions} assertions)\n";
