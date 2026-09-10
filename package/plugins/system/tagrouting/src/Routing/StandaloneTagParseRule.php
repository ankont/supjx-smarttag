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
use SuperSoft\Plugin\System\TagRouting\Tag\StandaloneMenuContextResolver;
use SuperSoft\Plugin\System\TagRouting\Tag\TagHierarchyRepository;

final readonly class StandaloneTagParseRule
{
    public function __construct(
        private SiteApplication $app,
        private TagHierarchyRepository $tags,
        private StandaloneMenuContextResolver $menus,
        private FacetDelimiter $markers,
    ) {
    }

    public function __invoke(SiteRouter &$router, Uri &$uri): void
    {
        $segments = array_values(array_filter(explode('/', trim($uri->getPath(), '/')), 'strlen'));
        [$routeSegments, $languageSegment, $language] = $this->withoutLanguagePrefix($segments);
        $marker = $this->markers->extract($routeSegments);
        $context = null;
        $aliases = [];
        $canonicalInput = false;

        if ($marker !== null) {
            $aliases = $marker['segments'];
            $context = $this->menus->find($language);

            if ($context !== null && !$context->handlesRootMarker()) {
                // Leave the URI and active menu untouched for the underlying
                // component router to interpret this as a filter expression.
                return;
            }

            $canonicalInput = $context !== null
                && $context->useMenuRouteAsCanonical
                && $context->routeSegments !== []
                    ? $context->routeSegments === [$marker['marker']]
                    : $marker['canonical'];
        } else {
            $context = $this->menus->match($routeSegments, $language);

            if ($context === null) {
                return;
            }

            $aliases = array_values(array_slice($routeSegments, count($context->routeSegments)));
            $canonicalInput = $context->usesMenuRoute();
        }

        $this->assertValidPath($aliases);
        $resolved = $this->tags->resolvePath(
            $aliases,
            array_values($this->app->getIdentity()->getAuthorisedViewLevels()),
            $language,
        );

        if ($resolved === null) {
            throw new RouteNotFoundException(Text::_('JERROR_PAGE_NOT_FOUND'));
        }

        $query = $uri->getQuery(true);
        $tainted = !$canonicalInput;

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

        if ($context !== null) {
            $query['Itemid'] = $context->itemId;
            $this->app->getMenu()->setActive($context->itemId);
        }

        $uri->setQuery($query);
        // Language Filter still owns the language prefix and consumes it later.
        $uri->setPath($languageSegment ?? '');

        if ($tainted && in_array($this->app->getInput()->getMethod(), ['GET', 'HEAD'], true) && method_exists($router, 'setTainted')) {
            $router->setTainted();
        }
    }

    /** @param list<string> $aliases */
    private function assertValidPath(array $aliases): void
    {
        if (
            $aliases === []
            || count($aliases) > 32
            || array_filter($aliases, static fn (string $alias): bool => strlen($alias) > 255) !== []
        ) {
            throw new RouteNotFoundException(Text::_('JERROR_PAGE_NOT_FOUND'));
        }
    }

    /**
     * @param list<string> $segments
     * @return array{list<string>,string|null,string}
     */
    private function withoutLanguagePrefix(array $segments): array
    {
        $language = $this->app->getLanguage()->getTag();
        $languages = PluginHelper::isEnabled('system', 'languagefilter') ? LanguageHelper::getLanguages('sef') : [];
        $candidate = $segments[0] ?? '';

        if (!isset($languages[$candidate])) {
            return [$segments, null, $language];
        }

        return [array_values(array_slice($segments, 1)), $candidate, (string) $languages[$candidate]->lang_code];
    }
}
