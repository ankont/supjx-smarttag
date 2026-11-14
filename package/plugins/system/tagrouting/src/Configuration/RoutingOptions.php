<?php
declare(strict_types=1);
namespace SuperSoft\Plugin\System\TagRouting\Configuration;

use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;

/** Values always belong to the SmartTag plugin, regardless of form location. */
final class RoutingOptions
{
    public const DEFAULTS = [
        'enable_cleanup'=>1, 'enable_hierarchical'=>0, 'enable_faceted'=>1,
        'facet_scope'=>'category', 'facet_include_descendants'=>0,
        'facet_max_groups'=>10, 'facet_max_or_options'=>10, 'facet_max_options'=>15, 'facet_max_bytes'=>1024,
        'accepted_markers'=>['!','!tag','!filter'], 'custom_marker'=>'', 'canonical_marker'=>'!',
        'show_breadcrumb_marker'=>1, 'breadcrumb_marker'=>'#',
    ];

    /** Convert submitted compact controls to the existing plugin parameter schema. */
    public static function normalize(array $values): array
    {
        $controls = $values['accepted_markers'] ?? null;
        if (is_array($controls) && (array_key_exists('custom_enabled', $controls) || array_key_exists('custom', $controls))) {
            $accepted = [];
            foreach (['short'=>'!', 'tag'=>'!tag', 'filter'=>'!filter'] as $key => $marker) {
                if (in_array($controls[$key] ?? '0', [1, '1'], true)) { $accepted[] = $marker; }
            }
            $rawCustom = $controls['custom'] ?? $values['custom_marker'] ?? '';
            $custom = is_string($rawCustom) ? trim($rawCustom) : '';
            $customEnabled = array_key_exists('custom_enabled', $controls)
                ? in_array($controls['custom_enabled'], [1, '1'], true) : $custom !== '';
            if ($customEnabled) { $accepted[] = '__custom__'; }
            $values['accepted_markers'] = $accepted;
            $values['custom_marker'] = $custom;
            $values['enable_custom_marker'] = $customEnabled ? 1 : 0;
        }
        return $values;
    }

    public static function merge(array $legacy): array
    {
        $legacy = self::normalize($legacy);
        $values = array_replace(self::DEFAULTS, array_intersect_key($legacy, self::DEFAULTS));
        if ((bool) (int) ($legacy['enable_custom_marker'] ?? 0)) {
            $markers = $values['accepted_markers'];
            $markers = is_array($markers) ? $markers : explode(',', (string) $markers);
            $values['accepted_markers'] = array_values(array_unique([...$markers, '__custom__']));
        }
        // New selection replaces the old independent custom-enable switch.
        $markers = $values['accepted_markers'];
        $values['enable_custom_marker'] = in_array('__custom__', is_array($markers) ? $markers : explode(',', (string) $markers), true) ? 1 : 0;
        return $values;
    }

    public static function get(): Registry
    {
        $legacy = new Registry(PluginHelper::getPlugin('system', 'tagrouting')->params ?? '');
        return new Registry(self::merge($legacy->toArray()));
    }

    public static function descendants(mixed $override): bool
    {
        return $override === null || $override === '' || $override === '-1' || $override === -1
            ? (bool) (int) self::get()->get('facet_include_descendants', 0)
            : (bool) (int) $override;
    }
}
