<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Routing;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Language\LanguageHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Exception\RouteNotFoundException;
use Joomla\CMS\Router\SiteRouter;
use Joomla\CMS\Uri\Uri;
use SuperSoft\Plugin\System\TagRouting\Tag\TagHierarchyRepository;
use SuperSoft\Plugin\System\TagRouting\Tag\TagMenuContextResolver;

final readonly class HierarchicalTagParseRule
{
    public function __construct(
        private SiteApplication $app,
        private TagHierarchyRepository $tags,
        private TagMenuContextResolver $menus,
        private string $prefix,
    ) {
    }

    public function __invoke(SiteRouter &$router, Uri &$uri): void
    {
        $segments = array_values(array_filter(explode('/', trim($uri->getPath(), '/')), 'strlen'));
        $prefixIndex = 0;
        $languageSegment = null;
        $language = $this->app->getLanguage()->getTag();

        if (($segments[0] ?? null) !== $this->prefix) {
            $languages = $this->languagesBySef();
            $candidate = $segments[0] ?? '';

            if (!isset($languages[$candidate]) || ($segments[1] ?? null) !== $this->prefix) {
                return;
            }

            $prefixIndex = 1;
            $languageSegment = $candidate;
            $language = (string) $languages[$candidate]->lang_code;
        }

        $aliases = array_values(array_slice($segments, $prefixIndex + 1));

        if (
            $aliases === []
            || count($aliases) > 32
            || array_filter($aliases, static fn (string $alias): bool => strlen($alias) > 255) !== []
        ) {
            throw new RouteNotFoundException(Text::_('JERROR_PAGE_NOT_FOUND'));
        }

        $resolved = $this->tags->resolvePath(
            $aliases,
            array_values($this->app->getIdentity()->getAuthorisedViewLevels()),
            $language,
        );

        if ($resolved === null) {
            throw new RouteNotFoundException(Text::_('JERROR_PAGE_NOT_FOUND'));
        }

        $query = $uri->getQuery(true);
        $tainted = false;

        foreach (['option', 'view', 'id', 'Itemid', TagPageState::REQUEST_KEY] as $key) {
            if (array_key_exists($key, $query)) {
                $tainted = true;
            }

            unset($query[$key]);
        }

        $query['option'] = 'com_tags';
        $query['view'] = 'tag';
        $query['id'] = [$resolved->id()];
        $query[TagPageState::REQUEST_KEY] = $resolved->id();
        $itemId = $this->menus->find($resolved->id(), $language);

        if ($itemId !== null) {
            $query['Itemid'] = $itemId;
            $this->app->getMenu()->setActive($itemId);
        }

        $uri->setQuery($query);
        // If Language Filter still has to run, leave only its leading segment.
        $uri->setPath($languageSegment ?? '');

        if (
            $tainted
            && $this->app->getInput()->getMethod() === 'GET'
            && method_exists($router, 'setTainted')
        ) {
            $router->setTainted();
        }
    }

    /** @return array<string,object> */
    private function languagesBySef(): array
    {
        return PluginHelper::isEnabled('system', 'languagefilter') ? LanguageHelper::getLanguages('sef') : [];
    }
}
