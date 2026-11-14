<?php
declare(strict_types=1);
namespace SuperSoft\Plugin\System\TagRouting\Routing;

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;
use SuperSoft\Plugin\System\TagRouting\Domain\FacetExpression;
use SuperSoft\Plugin\System\TagRouting\Tag\StandaloneFacetResolver;

final class TagFilterRoute
{
    /** Same validated scope for template controls and route resolution. */
    public static function available(int $itemId, ?string $language = null): array
    {
        $app = Factory::getApplication();
        try {
            return (new StandaloneFacetResolver($app, Factory::getContainer()->get(DatabaseInterface::class)))
                ->scope($itemId, $language ?? $app->getLanguage()->getTag());
        } catch (\InvalidArgumentException) { return ['roots'=>[], 'nodes'=>[]]; }
    }

    /** Fresh URL for a filter change. Empty expression clears all extra tags. */
    public static function get(int $tagId, int $itemId, string|FacetExpression $expression, array $parameters = []): string
    {
        if ($tagId < 1 || $itemId < 1) { return ''; }
        $app = Factory::getApplication();
        $expression = $expression instanceof FacetExpression ? $expression->expression() : $expression;
        try {
            $service = new StandaloneFacetResolver($app, Factory::getContainer()->get(DatabaseInterface::class));
            $language = (string) ($parameters['lang'] ?? $app->getLanguage()->getTag());
            $service->scope($itemId, $language);
            $state = $expression === '' ? null : $service->resolve($tagId, $itemId, $expression, $language);
        } catch (\InvalidArgumentException) { return ''; }
        $query = array_intersect_key($parameters, array_flip(['lang','cat-view','limit','content-types','content-category']));
        $query += ['option'=>'com_tags','view'=>'tag','id'=>[$tagId],'Itemid'=>$itemId];
        if ($state !== null) { $query[TagFilterRequest::KEY] = TagFilterRequest::encode($state); }
        return Route::_('index.php?' . http_build_query($query));
    }
}
