<?php

declare(strict_types=1);

// Isolated contracts with narrow Joomla doubles; actual frontend coverage lives
// in local_faceted_frontend.py. No database or Joomla runtime is needed here.
namespace Joomla\CMS\Component\Router\Rules { interface RulesInterface {} }
namespace Joomla\Database { interface DatabaseInterface {} }
namespace Joomla\Registry {
    class Registry {
        public function __construct(private mixed $values = []) {}
        public function toArray() { return is_array($this->values) ? $this->values : []; }
    }
}
namespace Joomla\CMS\Plugin {
    class PluginHelper { public static function getPlugin(...$args) { return (object) ['params' => []]; } }
}
namespace Joomla\CMS\Language { class Text { public static function _($key) { return $key; } } }
namespace Joomla\CMS\Router\Exception { class RouteNotFoundException extends \RuntimeException {} }
namespace Joomla\CMS\Router {
    class Route {
        public static array $calls = [];
        public static function _($query) {
            self::$calls[] = $query;
            if (!is_string($query) || !str_starts_with($query, 'index.php?')) {
                throw new \RuntimeException('Route builder would inherit current request variables.');
            }
            return $query;
        }
    }
}
namespace Joomla\CMS\Application {
    class SiteApplication {
        public array $values = [];
        public bool $tainted = false;
        public function __get($key) { return $this; }
        public function get($key, $default = null, $filter = null) { return $this->values[$key] ?? $default; }
        public function getInput() { return $this; }
        public function getCmd($key) { return $this->values[$key] ?? ''; }
        public function getString($key) { return $this->values[$key] ?? ''; }
        public function getInt($key) { return (int) ($this->values[$key] ?? 0); }
        public function getIdentity() { return $this; }
        public function getAuthorisedViewLevels() { return [1]; }
        public function getLanguage() { return $this; }
        public function getTag() { return 'el-GR'; }
        public function getRouter() { return $this; }
        public function setTainted() { $this->tainted = true; }
    }
}
namespace Joomla\CMS {
    class Factory {
        public static $app;
        public static function getApplication() { return self::$app; }
        public static function getContainer() { return new class {
            public function get($class) { return new class implements \Joomla\Database\DatabaseInterface {}; }
        }; }
    }
}
namespace Joomla\Component\Content\Site\Model {
    class ArticlesModel {
        public function getState($key) { return 9; }
        public function getPagination() { return new class {
            public array $params = [];
            public function setAdditionalUrlParam($key, $value) { $this->params[$key] = $value; }
        }; }
    }
}
namespace SuperSoft\Plugin\System\TagRouting\Infrastructure {
    class CategoryTaxonomyResolver {
        public static array $nodes;
        public function __construct(...$args) {}
        public static function forApplication(...$args) { return new self(); }
        public function resolve($category, $levels, $language) {
            if ($category !== 9 || $levels !== [1] || $language !== 'el-GR') {
                throw new \RuntimeException('Wrong scope, language or ACL context.');
            }
            return ['roots' => [self::$nodes[0]], 'nodes' => self::$nodes];
        }
    }
}
namespace {
    require __DIR__ . '/run.php';
    use Joomla\CMS\Factory;
    use Joomla\CMS\Application\SiteApplication;
    use SuperSoft\Plugin\System\TagRouting\Domain\TagNode;
    use SuperSoft\Plugin\System\TagRouting\Domain\FacetExpressionParser;
    use SuperSoft\Plugin\System\TagRouting\Domain\TagPathResolver;
    use SuperSoft\Plugin\System\TagRouting\Domain\TagExpressionNormalizer;
    use SuperSoft\Plugin\System\TagRouting\Domain\SerializedExpression;
    use SuperSoft\Plugin\System\TagRouting\Configuration\FacetLimits;
    use SuperSoft\Plugin\System\TagRouting\Infrastructure\CategoryTaxonomyResolver;
    use SuperSoft\Plugin\System\TagRouting\Routing\FacetedParseRule;
    use SuperSoft\Plugin\System\TagRouting\Routing\FacetedBuildRule;
    use SuperSoft\Plugin\System\TagRouting\Routing\FacetedRoute;
    use SuperSoft\Plugin\System\TagRouting\Routing\FacetDelimiter;
    use SuperSoft\Plugin\System\TagRouting\Site\Model\ArticlesModel;

    $app = Factory::$app = new SiteApplication();
    $scope = new CategoryTaxonomyResolver();
    CategoryTaxonomyResolver::$nodes = [new TagNode(1, 'a', 0, 1, 2, 1), new TagNode(2, 'b', 0, 3, 4, 2)];
    $serializer = new SerializedExpression();
    $rule = new FacetedParseRule($app, $scope, new FacetExpressionParser(new FacetLimits()), new TagPathResolver(), new TagExpressionNormalizer(), $serializer, new FacetDelimiter());
    foreach (['b+a', 'a,a+b', 'a+b+a', 'a+b'] as $raw) {
        $app->tainted = false;
        $segments = ['!', $raw];
        $vars = ['view' => 'category', 'id' => 9, 'limitstart' => 30, 'cat-view' => 'list', 'lang' => 'el-GR'];
        $rule->parse($segments, $vars);
        $state = $serializer->decode($vars['__tagrouting']);
        $assert($state['expression'] === 'a+b', 'Incoming expression was not canonical.');
        $assert($app->tainted === ($raw !== 'a+b'), 'Canonical route taint loop or missed redirect.');
        $assert($vars['limitstart'] === 30 && $vars['cat-view'] === 'list' && $vars['lang'] === 'el-GR', 'Redirect lost non-filter parameters.');
        $app->values = ['option' => 'com_content', 'view' => 'category', 'id' => 9, '__tagrouting' => $vars['__tagrouting'], 'cat-view' => 'list', 'lang' => 'el-GR', 'limitstart' => 30];
        $pagination = (new ArticlesModel())->getPagination();
        $assert($pagination->params['__tagrouting'] === $vars['__tagrouting'], 'Pagination dropped facet state.');
        $assert($pagination->params['lang'] === 'el-GR' && $pagination->params['cat-view'] === 'list' && !isset($pagination->params['limitstart']), 'Pagination presentation/offset ownership failed.');
        $app->values['__tagrouting'] = 'tampered after parse';
        $assert((new ArticlesModel())->getPagination()->params['__tagrouting'] === $vars['__tagrouting'], 'Pagination trusted mutable external input instead of validated state.');
    }
    $app->values['__tagrouting'] = '';
    \SuperSoft\Plugin\System\TagRouting\Routing\FacetedRequest::reset($app);
    $app->values['__tagrouting'] = $vars['__tagrouting'];
    $assert(\SuperSoft\Plugin\System\TagRouting\Routing\FacetedRequest::current() === null, 'External JSON was accepted as validated routing state.');
    $assert(!isset((new ArticlesModel())->getPagination()->params['__tagrouting']), 'Unfiltered pagination acquired facet state.');
    $expression = (new TagPathResolver())->resolve((new FacetExpressionParser())->parse('b+a'), CategoryTaxonomyResolver::$nodes);
    $url = FacetedRoute::get(9, $expression, 106, 'el-GR');
    parse_str(parse_url($url, PHP_URL_QUERY), $query);
    $assert(!isset($query['start'], $query['limitstart']) && $query['lang'] === 'el-GR', 'Filter builder leaked offset or dropped language.');
    $assert($serializer->decode($query['__tagrouting'])['expression'] === 'a+b', 'Builder did not directly emit canonical state.');
    $query['__tagrouting'] = $serializer->encode(9, $expression->tags(), $expression->tags(), 'b+a+a');
    $segments = [];
    (new FacetedBuildRule($serializer, new FacetDelimiter(), new FacetLimits(), $scope, $app))->build($query, $segments);
    $assert($segments === ['!', 'a+b'], 'Direct serialized builder was not canonical.');
    $tagUrl = \SuperSoft\Plugin\System\TagRouting\Routing\TagRoute::forTag(42, 'el-GR');
    parse_str(parse_url($tagUrl, PHP_URL_QUERY), $tagQuery);
    $assert(!isset($tagQuery['__tagrouting'], $tagQuery['start'], $tagQuery['limitstart']), 'Category-to-tag builder leaked filter state or offset.');
    echo "OK ({$assertions} assertions; isolated routing/pagination contracts)\n";
}
