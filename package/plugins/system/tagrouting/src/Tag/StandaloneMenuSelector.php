<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Tag;

final class StandaloneMenuSelector
{
    /**
     * @param list<StandaloneMenuContext> $contexts
     */
    public function best(array $contexts, string $language): ?StandaloneMenuContext
    {
        $contexts = $this->forLanguage($contexts, $language);
        usort($contexts, static fn (StandaloneMenuContext $a, StandaloneMenuContext $b): int => [
            $a->language === $language ? 0 : 1,
            $a->itemId,
        ] <=> [
            $b->language === $language ? 0 : 1,
            $b->itemId,
        ]);

        return $contexts[0] ?? null;
    }

    /**
     * A real com_tagrouting menu route is an explicit namespace boundary. The
     * longest matching route wins; parent menu component types are irrelevant.
     *
     * @param list<string> $requestSegments
     * @param list<StandaloneMenuContext> $contexts
     */
    public function match(array $requestSegments, array $contexts, string $language): ?StandaloneMenuContext
    {
        $matches = array_values(array_filter(
            $this->forLanguage($contexts, $language),
            static function (StandaloneMenuContext $context) use ($requestSegments): bool {
                $count = count($context->routeSegments);

                return $count > 0
                    && count($requestSegments) > $count
                    && array_slice($requestSegments, 0, $count) === $context->routeSegments;
            },
        ));

        usort($matches, static fn (StandaloneMenuContext $a, StandaloneMenuContext $b): int => [
            -count($a->routeSegments),
            $a->language === $language ? 0 : 1,
            $a->itemId,
        ] <=> [
            -count($b->routeSegments),
            $b->language === $language ? 0 : 1,
            $b->itemId,
        ]);

        return $matches[0] ?? null;
    }

    /**
     * @param list<StandaloneMenuContext> $contexts
     * @return list<StandaloneMenuContext>
     */
    private function forLanguage(array $contexts, string $language): array
    {
        return array_values(array_filter(
            $contexts,
            static fn (StandaloneMenuContext $context): bool => in_array($context->language, ['*', $language], true),
        ));
    }
}
