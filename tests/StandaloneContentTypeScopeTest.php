<?php
declare(strict_types=1);

namespace Joomla\Registry {
    class Registry {
        public function __construct(private array $values = []) {}
        public function get($key, $default = null) { return $this->values[$key] ?? $default; }
    }
}
namespace Joomla\CMS\Menu {
    class AbstractMenu {
        public array $items = [];
        public ?object $active = null;
        public function getItems(...$args) { return array_values(array_filter($this->items, fn($i) => $i->component === 'com_tagrouting')); }
        public function getItem($id) { return $this->items[$id] ?? null; }
        public function getActive() { return $this->active; }
        public function setActive($id) { return $this->active = $this->getItem($id); }
    }
}
namespace Joomla\CMS\Application {
    class SiteApplication {
        public function __construct(public $menu) {}
        public function getMenu() { return $this->menu; }
        public function getLanguage() { return $this; }
        public function getTag() { return 'el-GR'; }
        public function getIdentity() { return $this; }
        public function getAuthorisedViewLevels() { return [1]; }
        public function getInput() { return $this; }
        public function getMethod() { return 'GET'; }
    }
}
namespace Joomla\CMS\Router {
    class SiteRouter { public bool $tainted = false; public function setTainted() { $this->tainted = true; } }
}
namespace Joomla\CMS\Router\Exception { class RouteNotFoundException extends \RuntimeException {} }
namespace Joomla\CMS\Language { class Text { public static function _($v) { return $v; } } }
namespace Joomla\CMS\Plugin {
    class PluginHelper { public static function isEnabled(...$args) { return false; } }
}
namespace Joomla\CMS\Uri {
    class Uri {
        public function __construct(public array $query = [], public string $path = '') {}
        public function getQuery($array = false) { return $this->query; }
        public function setQuery($query) { $this->query = $query; }
        public function setVar($key, $value) { $this->query[$key] = $value; }
        public function getPath() { return $this->path; }
        public function setPath($path) { $this->path = $path; }
    }
}
namespace SuperSoft\Plugin\System\TagRouting\Tag {
    class TagHierarchyRepository {
        public function pathForId($id, $levels, $language) {
            if ($levels !== [1] || $language !== 'el-GR') { throw new \RuntimeException('ACL/language context lost.'); }
            return new class {
                public function aliases() { return ['age-group', 'primary']; }
                public function id() { return 7; }
            };
        }
        public function resolvePath($aliases, $levels, $language) {
            if ($aliases !== ['age-group','primary']) { throw new \RuntimeException('Hierarchy order changed.'); }
            return $this->pathForId(7, $levels, $language);
        }
    }
}
namespace {
    require __DIR__ . '/run.php';
    use Joomla\CMS\Menu\AbstractMenu;
    use Joomla\CMS\Application\SiteApplication;
    use Joomla\CMS\Router\SiteRouter;
    use Joomla\CMS\Uri\Uri;
    use SuperSoft\Plugin\System\TagRouting\Tag\ContentTypeScope;
    use SuperSoft\Plugin\System\TagRouting\Tag\StandaloneMenuContextResolver;
    use SuperSoft\Plugin\System\TagRouting\Tag\TagHierarchyRepository;
    use SuperSoft\Plugin\System\TagRouting\Routing\{StandaloneTagPrepareBuildRule, StandaloneTagBuildRule, StandaloneTagParseRule, FacetDelimiter};

    $assert(ContentTypeScope::normalize(['3,2','2',0,[],null,'bad']) === [2,3], 'Native comma-separated type IDs not normalized.');
    $menu = new AbstractMenu();
    $make = static function ($id, $route, $types, $native = false) {
        return new class($id, $route, $types, $native) {
            public string $language = '*';
            public string $component;
            public array $query;
            public function __construct(public int $id, public string $route, public array $types, bool $native) {
                $this->component = $native ? 'com_tags' : 'com_tagrouting';
                $this->query = $native ? ['option'=>'com_tags','view'=>'tag','types'=>$types,'id'=>[7]] : ['option'=>'com_tagrouting','view'=>'tags'];
            }
            public function getParams() { return new \Joomla\Registry\Registry(['content_types'=>$this->types]); }
        };
    };
    $menu->items = [10=>$make(10,'tag',[]), 20=>$make(20,'article-tags',[1]), 30=>$make(30,'mixed-tags',[2,1]), 40=>$make(40,'other-articles',[1]), 50=>$make(50,'native',[3],true)];
    $app = new SiteApplication($menu);
    $contexts = new StandaloneMenuContextResolver($menu);
    $assert($contexts->find('el-GR')->itemId === 10, 'Generic routes inherited a scoped menu.');
    $assert($contexts->find('el-GR',[1])->itemId === 20, 'Exact scope not found.');
    $assert($contexts->find('el-GR',[1],40)->itemId === 40, 'Explicit namespace not preserved.');
    $assert($contexts->find('el-GR',[1,2,2])->itemId === 30, 'Multi-type set ordering matters.');
    $assert(!$contexts->find('el-GR',[1])->handlesRootMarker(), 'Scoped menu captured generic marker.');
    $prepare = new StandaloneTagPrepareBuildRule($app);
    $build = new StandaloneTagBuildRule($app, new TagHierarchyRepository(), $contexts, new FacetDelimiter());
    $parse = new StandaloneTagParseRule($app, new TagHierarchyRepository(), $contexts, new FacetDelimiter());
    $buildUrl = static function ($query) use ($prepare, $build) {
        $router = new SiteRouter(); $uri = new Uri($query);
        $prepare($router,$uri);
        // Emulate Joomla/menu rules consuming the original public parameters.
        unset($uri->query['id'],$uri->query['types'],$uri->query['Itemid']);
        $build($router,$uri);
        return $uri;
    };
    $base = ['option'=>'com_tags','view'=>'tag','id'=>[7]];
    foreach ([[null,[], 'tag',null], [20,[], 'article-tags',null], [40,[], 'other-articles',null], [null,['types'=>[2,1]],'mixed-tags',null], [50,[],'tag',[3]]] as [$item,$extra,$prefix,$residual]) {
        $menu->active = null;
        $query = $base + $extra + ($item ? ['Itemid'=>$item] : []) + ['limitstart'=>30,'lang'=>'el-GR','format'=>'feed'];
        $uri = $buildUrl($query);
        $assert($uri->path === $prefix.'/age-group/primary', 'Builder lost scope or hierarchy.');
        $assert(($uri->query['types'] ?? null) === $residual, 'Native restriction lost or redundant types leaked.');
        $assert($uri->query['limitstart'] === 30 && $uri->query['lang'] === 'el-GR' && $uri->query['format'] === 'feed', 'Pagination/feed parameters lost.');
        $assert(!isset($uri->query['__tagrouting_scope']), 'Internal scope escaped into public URLs.');
    }
    $menu->active = $menu->items[10];
    $query = $base + ['Itemid'=>10];
    $query['__tagrouting_scope'] = \SuperSoft\Plugin\System\TagRouting\Routing\StandaloneTagScope::capture($menu,$query);
    // Native preprocess can substitute an article-restricted Itemid afterwards.
    $query['Itemid'] = 50;
    $assert($buildUrl($query)->path === 'tag/age-group/primary', 'Core preprocess replaced captured generic scope with native restriction.');
    $menu->active = $menu->items[20];
    $assert($buildUrl($base)->path === 'article-tags/age-group/primary', 'Hierarchy navigation lost active scope.');
    // Virtual root temporarily delegates with a com_tags query; component identity survives.
    $menu->active->query = ['option'=>'com_tags','view'=>'tag','id'=>[]];
    $assert($buildUrl($base)->path === 'article-tags/age-group/primary', 'Delegated virtual root lost namespace.');
    $freshRootContexts = new StandaloneMenuContextResolver($menu);
    $assert($freshRootContexts->find('el-GR',[1],20)?->itemId === 20, 'First route build during virtual-root delegation lost its namespace.');
    $menu->active->query = ['option'=>'com_tagrouting','view'=>'tags'];
    foreach ([['article-tags/age-group/primary',20],['mixed-tags/age-group/primary',30],['tag/age-group/primary',10]] as [$path,$id]) {
        $uri = new Uri(['start'=>30,'lang'=>'el-GR'], $path); $router = new SiteRouter();
        $parse($router,$uri);
        $assert(!$router->tainted && $menu->active->id === $id, 'Canonical namespace parsed incorrectly.');
        $assert($buildUrl($uri->query)->path === $path, 'Canonical round trip would loop/change namespace.');
    }
    $uri = new Uri(['types'=>[2],'start'=>30], 'article-tags/age-group/primary'); $router = new SiteRouter();
    $parse($router,$uri);
    $assert($router->tainted && !isset($uri->query['types']), 'Query types could override a scoped namespace.');
    $canonical = $buildUrl($uri->query);
    $router = new SiteRouter(); $parse($router,$canonical);
    $assert(!$router->tainted, 'Scoped query cleanup redirects repeatedly.');
    $onlyScoped = new AbstractMenu(); $onlyScoped->items = [20=>$menu->items[20]];
    $onlyScoped->active = $onlyScoped->items[20];
    $markerApp = new SiteApplication($onlyScoped); $markerContexts = new StandaloneMenuContextResolver($onlyScoped);
    $markerParser = new StandaloneTagParseRule($markerApp, new TagHierarchyRepository(), $markerContexts, new FacetDelimiter());
    $markerUri = new Uri([], '!/age-group/primary'); $markerRouter = new SiteRouter();
    $markerParser($markerRouter,$markerUri);
    $markerScope = \SuperSoft\Plugin\System\TagRouting\Routing\StandaloneTagScope::capture($onlyScoped,$base);
    $assert($markerScope['types'] === [], 'Unbound marker inherited a scoped home/active menu.');
    \SuperSoft\Plugin\System\TagRouting\Routing\TagPageState::reset($onlyScoped);
    $assert(\SuperSoft\Plugin\System\TagRouting\Routing\StandaloneTagScope::capture($onlyScoped,$base)['types'] === [1], 'Parse context survived explicit request reset.');
    echo "OK ({$assertions} assertions; isolated standalone type-scope routing)\n";
}
