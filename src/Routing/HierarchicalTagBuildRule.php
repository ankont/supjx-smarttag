<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Routing;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Language\LanguageHelper;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\SiteRouter;
use Joomla\CMS\Uri\Uri;
use SuperSoft\Plugin\System\TagRouting\Tag\TagHierarchyRepository;

final readonly class HierarchicalTagBuildRule
{
    public function __construct(
        private SiteApplication $app,
        private TagHierarchyRepository $tags,
        private string $prefix,
    ) {
    }

    public function __invoke(SiteRouter &$router, Uri &$uri): void
    {
        $query = $uri->getQuery(true);
        $tagId = TagPageState::id($query[TagPageState::REQUEST_KEY] ?? null);

        if ($tagId === null) {
            return;
        }

        $language = is_string($query['lang'] ?? null)
            ? $query['lang']
            : $this->app->getLanguage()->getTag();
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

        array_push($canonical, $this->prefix, ...$resolved->aliases());
        $uri->setPath(implode('/', $canonical));

        foreach (['option', 'view', 'id', 'Itemid', 'layout'] as $key) {
            unset($query[$key]);
        }

        $uri->setQuery($query);
    }
}
