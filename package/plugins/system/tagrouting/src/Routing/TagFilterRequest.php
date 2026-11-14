<?php
declare(strict_types=1);
namespace SuperSoft\Plugin\System\TagRouting\Routing;

use Joomla\CMS\Factory;

/** Validated per-request state only; no fallback to external query input. */
final class TagFilterRequest
{
    public const KEY = '__tagrouting_extra';
    private static ?\WeakMap $states = null;

    public static function reset(object $app): void
    {
        if (self::$states !== null) { unset(self::$states[$app]); }
    }

    public static function remember(object $app, array $state): void
    {
        self::$states ??= new \WeakMap();
        self::$states[$app] = $state;
    }

    public static function current(): ?array
    {
        $app = Factory::getApplication();
        $state = self::$states[$app] ?? null;
        $input = $app->getInput();
        $ids = (array) $input->get('id', [], 'array');
        return $state !== null && $input->getCmd('option') === 'com_tags' && $input->getCmd('view') === 'tag'
            && count($ids) === 1 && (int) reset($ids) === $state['tag']
            && $input->getInt('Itemid') === $state['item'] ? $state : null;
    }

    public static function encode(array $state): string
    {
        return json_encode(array_intersect_key($state, array_flip(['tag','item','expression'])), JSON_THROW_ON_ERROR);
    }

    public static function decode(mixed $value): ?array
    {
        if (!is_string($value) || strlen($value) > 4096) { return null; }
        $state = json_decode($value, true, 8);
        return is_array($state) && is_int($state['tag'] ?? null) && $state['tag'] > 0
            && is_int($state['item'] ?? null) && $state['item'] > 0 && is_string($state['expression'] ?? null)
            ? $state : null;
    }
}
