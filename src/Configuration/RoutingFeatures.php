<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Configuration;

use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;

final readonly class RoutingFeatures
{
    public const DEFAULT_PREFIX = 'tags';

    public function __construct(
        public bool $canonicalCleanup,
        public bool $hierarchicalTags,
        public bool $facetedCategories,
        public string $tagPrefix,
    ) {
    }

    /** @param array<string,mixed> $values */
    public static function fromArray(array $values): self
    {
        return new self(
            self::enabled($values, 'enable_cleanup', true),
            self::enabled($values, 'enable_hierarchical', false),
            self::enabled($values, 'enable_faceted', true),
            self::safePrefix((string) ($values['tag_url_prefix'] ?? self::DEFAULT_PREFIX)),
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

    private static function safePrefix(string $prefix): string
    {
        $prefix = strtolower(trim($prefix, " \t\n\r\0\x0B/"));

        return preg_match('/^[a-z0-9][a-z0-9-]*$/D', $prefix) === 1 ? $prefix : self::DEFAULT_PREFIX;
    }
}
