<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Configuration;

use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;
use SuperSoft\Plugin\System\TagRouting\Domain\FacetExpression;
use SuperSoft\Plugin\System\TagRouting\Domain\FacetExpressionParser;
use SuperSoft\Plugin\System\TagRouting\Domain\TagExpression;

/** Shared limits for incoming requests, route builders and template controls. */
final readonly class FacetLimits
{
    public int $maxGroups;
    public int $maxOrOptions;
    public int $maxOptions;
    public int $maxBytes;

    public function __construct(int $maxGroups = 10, int $maxOrOptions = 10, int $maxOptions = 15, int $maxBytes = 1024)
    {
        $this->maxGroups = max(1, min(16, $maxGroups));
        $this->maxOrOptions = max(1, min(32, $maxOrOptions));
        $this->maxOptions = max(1, min(512, $maxOptions));
        $this->maxBytes = max(1, min(2048, $maxBytes));
    }

    public static function fromArray(array $values): self
    {
        return new self(
            (int) ($values['facet_max_groups'] ?? 10),
            (int) ($values['facet_max_or_options'] ?? 10),
            (int) ($values['facet_max_options'] ?? 15),
            (int) ($values['facet_max_bytes'] ?? 1024),
        );
    }

    public static function fromPlugin(): self
    {
        $plugin = PluginHelper::getPlugin('system', 'tagrouting');

        return self::fromArray((new Registry($plugin->params ?? ''))->toArray());
    }

    /** Hierarchical paths count as one option; repeated options also count. */
    public function allows(string|FacetExpression|TagExpression $expression): bool
    {
        $raw = is_string($expression) ? $expression : $expression->expression();

        return (new FacetExpressionParser($this))->parse($raw) !== null;
    }
}
