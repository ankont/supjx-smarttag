<?php
declare(strict_types=1);

namespace Joomla\CMS\Application {
    class SiteApplication {
        public string $method = 'GET';
        public function getInput() { return $this; }
        public function getMethod() { return $this->method; }
    }
}
namespace Joomla\CMS\Router {
    class SiteRouter {
        public bool $tainted = false;
        public function setTainted() { $this->tainted = true; }
    }
}
namespace Joomla\CMS\Uri {
    class Uri {
        public function __construct(public array $query) {}
        public function getQuery($array = false) { return $this->query; }
        public function setQuery($query) { $this->query = $query; }
        public function delVar($key) { unset($this->query[$key]); }
    }
}
namespace {
    require __DIR__ . '/run.php';
    $app = new \Joomla\CMS\Application\SiteApplication();
    $rule = new \SuperSoft\Plugin\System\TagRouting\Routing\InternalQueryBoundary($app);
    foreach (['{"v":2}', '{\\"v\\":2}', '', ['injected']] as $value) {
        $uri = new \Joomla\CMS\Uri\Uri(['__tagrouting' => $value, '__tagrouting_tag' => 42, '__tagrouting_scope' => ['types'=>[3]], 'cat-view' => 'details', 'lang' => 'el-GR', 'start' => 30, 'format' => 'feed']);
        $router = new \Joomla\CMS\Router\SiteRouter();
        $rule->parse($router, $uri);
        $assert($router->tainted, 'External internal keys did not taint the route.');
        $assert($uri->query === ['cat-view' => 'details', 'lang' => 'el-GR', 'start' => 30, 'format' => 'feed'], 'Boundary dropped legitimate parameters.');
        $router->tainted = false;
        $rule->parse($router, $uri);
        $assert(!$router->tainted, 'Clean canonical URL was tainted again.');
    }
    $uri = new \Joomla\CMS\Uri\Uri(['__tagrouting' => 'internal', '__tagrouting_tag' => 1, '__tagrouting_scope' => ['types'=>[3]], 'format' => 'feed', 'start' => 30]);
    $rule->build($router, $uri);
    $assert($uri->query === ['format' => 'feed', 'start' => 30], 'Outgoing boundary failed.');
    $cleaner = new \SuperSoft\Plugin\System\TagRouting\Routing\StandaloneTagQueryCleaner();
    $assert($cleaner->clean(['__tagrouting' => 'anything', 'cat-view' => 'details']) === ['cat-view' => 'details'], 'Standalone builder retained internal state.');
    echo "OK ({$assertions} assertions; external/internal query boundary)\n";
}
