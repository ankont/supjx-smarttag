<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Configuration;

use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;

final readonly class RoutingFeatures
{
    /** @var list<string> */
    public const DEFAULT_ACCEPTED_MARKERS = ['!', '!tag', '!filter'];

    public const DEFAULT_CANONICAL_MARKER = '!';
    public const DEFAULT_BREADCRUMB_MARKER = '#';

    /** @var list<string> */
    public array $acceptedMarkers;

    public function __construct(
        public bool $canonicalCleanup,
        public bool $hierarchicalTags,
        public bool $facetedCategories,
        array $acceptedMarkers,
        public string $canonicalMarker,
        public string $breadcrumbMarker,
    ) {
        $this->acceptedMarkers = array_values($acceptedMarkers);
    }

    /** @param array<string,mixed> $values */
    public static function fromArray(array $values): self
    {
        $customMarker = self::safeMarker((string) ($values['custom_marker'] ?? ''));
        $accepted = self::accepted($values['accepted_markers'] ?? self::DEFAULT_ACCEPTED_MARKERS);

        if (self::enabled($values, 'enable_custom_marker', false) && $customMarker !== null) {
            $accepted[] = $customMarker;
        }

        $accepted = array_values(array_unique($accepted));

        if ($accepted === []) {
            // A routing feature cannot build a canonical URL without at least
            // one marker. This also protects upgrades with malformed params.
            $accepted = [self::DEFAULT_CANONICAL_MARKER];
        }

        $canonical = (string) ($values['canonical_marker'] ?? self::DEFAULT_CANONICAL_MARKER);

        if ($canonical === '__custom__') {
            $canonical = $customMarker ?? '';
        }

        if (!in_array($canonical, $accepted, true)) {
            $canonical = in_array(self::DEFAULT_CANONICAL_MARKER, $accepted, true)
                ? self::DEFAULT_CANONICAL_MARKER
                : $accepted[0];
        }

        return new self(
            self::enabled($values, 'enable_cleanup', true),
            self::enabled($values, 'enable_hierarchical', false),
            self::enabled($values, 'enable_faceted', true),
            $accepted,
            $canonical,
            self::breadcrumbMarker((string) ($values['breadcrumb_marker'] ?? self::DEFAULT_BREADCRUMB_MARKER)),
        );
    }

    public static function fromRegistry(Registry $params): self
    {
        return self::fromArray($params->toArray());
    }

    public static function fromPlugin(): self
    {
        $plugin = PluginHelper::getPlugin('system', 'tagrouting');

        return self::fromRegistry(new Registry($plugin->params ?? ''));
    }

    public function needsContentRouter(): bool
    {
        return $this->canonicalCleanup || $this->facetedCategories;
    }

    public function needsSiteRules(): bool
    {
        return $this->canonicalCleanup || $this->hierarchicalTags;
    }

    private static function enabled(array $values, string $key, bool $default): bool
    {
        return array_key_exists($key, $values) ? (bool) (int) $values[$key] : $default;
    }

    /** @return list<string> */
    private static function accepted(mixed $value): array
    {
        $values = is_array($value) ? $value : preg_split('/\s*,\s*/', (string) $value, -1, PREG_SPLIT_NO_EMPTY);
        $accepted = [];

        foreach ($values ?: [] as $marker) {
            $marker = (string) $marker;

            if (in_array($marker, self::DEFAULT_ACCEPTED_MARKERS, true)) {
                $accepted[] = $marker;
            }
        }

        return array_values(array_unique($accepted));
    }

    private static function safeMarker(string $marker): ?string
    {
        $marker = strtolower(trim($marker, " \t\n\r\0\x0B/"));

        return preg_match('/^![a-z0-9-]*$/D', $marker) === 1 ? $marker : null;
    }

    private static function breadcrumbMarker(string $marker): string
    {
        $marker = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $marker) ?? '');

        return strlen($marker) <= 64 ? $marker : self::DEFAULT_BREADCRUMB_MARKER;
    }
}
