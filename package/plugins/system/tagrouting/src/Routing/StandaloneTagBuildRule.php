<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Routing;

use Joomla\CMS\Application\SiteApplication;
use SuperSoft\Plugin\System\TagRouting\Tag\ContentTypeScope;
use Joomla\CMS\Language\LanguageHelper;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\SiteRouter;
use Joomla\CMS\Uri\Uri;
use SuperSoft\Plugin\System\TagRouting\Tag\StandaloneMenuContextResolver;
use SuperSoft\Plugin\System\TagRouting\Tag\TagHierarchyRepository;

final readonly class StandaloneTagBuildRule
{
    private StandaloneTagQueryCleaner $queryCleaner;

    public function __construct(
        private SiteApplication $app,
        private TagHierarchyRepository $tags,
        private StandaloneMenuContextResolver $menus,
        private FacetDelimiter $markers,
        ?StandaloneTagQueryCleaner $queryCleaner = null,
    ) {
        $this->queryCleaner = $queryCleaner ?? new StandaloneTagQueryCleaner();
    }

    public function __invoke(SiteRouter &$router, Uri &$uri): void
    {
        $query = $uri->getQuery(true);
        $tagId = TagPageState::id($query[TagPageState::REQUEST_KEY] ?? null);

        if ($tagId === null) {
            return;
        }

        $language = is_string($query['lang'] ?? null) ? $query['lang'] : $this->app->getLanguage()->getTag();
        $resolved = $this->tags->pathForId(
            $tagId,
            array_values($this->app->getIdentity()->getAuthorisedViewLevels()),
            $language,
        );

        unset($query[TagPageState::REQUEST_KEY]);

        if ($resolved === null) {
            $uri->setQuery($query);

            return;
        }

        $built = array_values(array_filter(explode('/', trim($uri->getPath(), '/')), 'strlen'));
        $canonical = [];

        if (($built[0] ?? null) === 'index.php') {
            $canonical[] = array_shift($built);
        }

        $languages = PluginHelper::isEnabled('system', 'languagefilter') ? LanguageHelper::getLanguages('sef') : [];

        if (isset($languages[$built[0] ?? ''])) {
            $canonical[] = $built[0];
        }

        $scope = $query[TagPageState::SCOPE_KEY] ?? [];
        $types = ContentTypeScope::normalize(is_array($scope) ? ($scope['types'] ?? []) : []);
        $preferred = is_array($scope) ? TagPageState::id($scope['item'] ?? null) : null;
        $context = $this->menus->find($language, $types, $preferred);
        // No namespace for this native type restriction: retain it as public query.
        if ($context === null && $types !== []) {
            $context = $this->menus->find($language);
            $query['types'] = $types;
        } else {
            unset($query['types']);
        }
        $base = $context !== null && $context->usesMenuRoute()
            ? $context->routeSegments
            : [$this->markers->canonical()];
        array_push($canonical, ...$base, ...$resolved->aliases());
        $uri->setPath(implode('/', $canonical));

        $uri->setQuery($this->queryCleaner->clean($query));
    }
}
