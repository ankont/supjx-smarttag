<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Routing;

use Joomla\CMS\Factory;
use SuperSoft\Plugin\System\TagRouting\Domain\SerializedExpression;

final class FacetedRequest
{
    /** @var \WeakMap<object,array{serialized:string,state:array}>|null */
    private static ?\WeakMap $validated = null;

    /** @internal Called once on site application initialisation. */
    public static function reset(object $app): void
    {
        if (self::$validated !== null) { unset(self::$validated[$app]); }
    }

    /** @internal Only the scoped faceted parse rule records validated state. */
    public static function remember(object $app, string $serialized): void
    {
        $state = (new SerializedExpression())->decode($serialized);
        if ($state === null) { throw new \InvalidArgumentException('Invalid resolved facet state.'); }
        self::$validated ??= new \WeakMap();
        self::$validated[$app] = ['serialized' => $serialized, 'state' => $state];
    }

    /** For internal PHP pagination/build consumers; never copy external input. */
    public static function serialized(): ?string
    {
        return self::current() !== null ? self::$validated[Factory::getApplication()]['serialized'] : null;
    }
    /**
     * Gives template overrides read-only access to the resolved expression and
     * per-tag taxonomy root metadata without exposing router implementation.
     *
     * @return array{category:int,expression:string,segments:list<string>,groups:list<list<int>>,resolved:list<list<array{id:int,alias:string,root:int>>>}|null
     */
    public static function current(): ?array
    {
        $app = Factory::getApplication();
        $state = self::$validated[$app]['state'] ?? null;
        $input = $app->getInput();

        return $state !== null && $input->getCmd('option') === 'com_content'
            && $input->getCmd('view') === 'category' && (int) $input->getInt('id') === $state['category']
                ? $state : null;
    }
}
