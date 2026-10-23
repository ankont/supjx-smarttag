<?php
declare(strict_types=1);

namespace Joomla\Registry {
    class Registry {
        public function __construct(private array $values = []) {}
        public function get($key, $default = null) { return $this->values[$key] ?? $default; }
        public function set($key, $value) { $this->values[$key] = $value; }
    }
}
namespace Joomla\CMS { class Factory { public static $app; public static function getApplication() { return self::$app; } } }
namespace Joomla\Component\Tags\Site\Model {
    class TagModel {
        public \Joomla\Registry\Registry $state;
        private bool $populated = false;
        private bool $populating = false;
        public function __construct() { $this->state = new \Joomla\Registry\Registry(); }
        protected function populateState($ordering = 'c.core_title', $direction = 'ASC') {
            $this->setState('list.limit', \Joomla\CMS\Factory::$app->format === 'feed' ? 8 : 500);
            $this->setState('list.start', 30);
            $this->setState('params', \Joomla\CMS\Factory::$app->params);
        }
        public function setState($key, $value) { $this->state->set($key, $value); }
        public function getPagination() { return new class {
            public array $params = [];
            public function setAdditionalUrlParam($key, $value) { $this->params[$key] = $value; }
        }; }
        public function getState($key) {
            if (!$this->populated) {
                if ($this->populating) { throw new \RuntimeException('Recursive lazy state population.'); }
                $this->populating = true;
                $this->populateState();
                $this->populated = true;
                $this->populating = false;
            }
            return $this->state->get($key);
        }
    }
}
namespace {
    require __DIR__ . '/run.php';
    $app = \Joomla\CMS\Factory::$app = new class {
        public string $format = 'html';
        public string $view = 'tag';
        public $menu;
        public $params;
        public array $query = [];
        public array $posted = [];
        public string $derivedView = 'details';
        public function __get($key) { return new class($this, $key) {
            public function __construct(private object $app, private string $kind) {}
            public function get($key, $default = null, $filter = null) { return ($this->kind === 'post' ? $this->app->posted : $this->app->query)[$key] ?? $default; }
        }; }
        public function get($key, $default = null, $filter = null) { return $key === 'list_limit' ? 500 : ($this->query[$key] ?? $default); }
        public function getInput() { return $this; }
        public function getMenu() { return $this; }
        public function getActive() { return $this->menu; }
        public function getCmd($key) { return $key === 'view' ? $this->view : ($key === 'option' ? 'com_tags' : ($key === 'cat-view' ? $this->derivedView : '')); }
        public function getWord($key) { return $this->format; }
    };
    foreach ([[null,30], [60,60], ['',500], [0,500], [-1,30], [999,500]] as [$value, $expected]) {
        $app->params = new \Joomla\Registry\Registry(['display_num'=>500,'show_pagination_limit'=>1]);
        $app->menu = new class($value) {
            public array $query = ['option'=>'com_tagrouting','view'=>'tags'];
            public function __construct(private $value) {}
            public function getParams() { return new \Joomla\Registry\Registry($this->value === null ? [] : ['tag_page_limit'=>$this->value]); }
        };
        $model = new \SuperSoft\Plugin\System\TagRouting\Tags\Site\Model\TagModel();
        $method = new \ReflectionMethod($model, 'populateState');
        $model->getState('list.limit');
        $assert($model->getState('list.limit') === $expected, 'Menu page size lost to global/session/URL size.');
        $assert($model->getState('list.start') === 30, 'Pagination offset was changed.');
        $assert($model->getState('params')->get('show_pagination_limit') === 1, 'Native selector preference was overridden.');
        $assert($app->params->get('display_num') === 500 && $app->params->get('show_pagination_limit') === 1, 'Shared params were mutated.');
    }
    $app->menu = new class {
        public array $query = ['option'=>'com_tagrouting','view'=>'tags'];
        public function getParams() { return new \Joomla\Registry\Registry(['tag_page_limit'=>30]); }
    };
    foreach ([['10',10], ['500',500], ['0',0], ['-1',30], ['invalid',30], [['50'],30]] as [$raw, $expected]) {
        $app->query = ['limit' => $raw];
        $model = new \SuperSoft\Plugin\System\TagRouting\Tags\Site\Model\TagModel();
        $method = new \ReflectionMethod($model, 'populateState');
        $assert($model->getState('list.limit') === $expected, 'URL limit precedence/validation failed.');
        $pagination = $model->getPagination();
        if (is_string($raw) && ctype_digit($raw)) {
            $assert(($pagination->params['limit'] ?? null) === (int) $raw, 'Explicit limit lost during pagination.');
        } else {
            $assert(!isset($pagination->params['limit']), 'Invalid URL limit was propagated.');
        }
    }
    $app->query = [];
    $app->posted = ['limit'=>'12'];
    $postedModel = new \SuperSoft\Plugin\System\TagRouting\Tags\Site\Model\TagModel();
    $assert($postedModel->getState('list.limit') === 12 && $postedModel->getPagination()->params['limit'] === 12, 'Native posted limit selection was ignored.');
    $app->query = ['limit'=>'10'];
    $urlModel = new \SuperSoft\Plugin\System\TagRouting\Tags\Site\Model\TagModel();
    $assert($urlModel->getState('list.limit') === 10, 'GET did not take precedence over POST.');
    $app->query = $app->posted = [];
    $assert(!isset((new \SuperSoft\Plugin\System\TagRouting\Tags\Site\Model\TagModel())->getPagination()->params['cat-view']), 'Session view preference leaked into pagination.');
    $app->query = ['cat-view'=>'icons'];
    $assert((new \SuperSoft\Plugin\System\TagRouting\Tags\Site\Model\TagModel())->getPagination()->params['cat-view'] === 'icons', 'Explicit view preference was lost.');
    $app->query = [];
    $app->format = 'feed'; $method->invoke($model);
    $assert($model->getState('list.limit') === 8, 'Feed limit was overridden.');
    $app->format = 'html'; $app->menu->query['option'] = 'com_tags'; $method->invoke($model);
    $assert($model->getState('list.limit') === 500, 'Native tag menu was overridden.');
    $app->menu = null; $method->invoke($model);
    $assert($model->getState('list.limit') === 500, 'Missing menu context was overridden.');
    $app->menu = new class {
        public array $query = ['option'=>'com_tagrouting','view'=>'tags'];
        public array $types = [3];
        public function getParams() { return new \Joomla\Registry\Registry(['content_types'=>$this->types]); }
    };
    foreach ([[3], [4,3,3], []] as $types) {
        $app->menu->types = $types;
        foreach (['html','feed'] as $format) {
            $app->format = $format;
            $model = new \SuperSoft\Plugin\System\TagRouting\Tags\Site\Model\TagModel();
            $scope = \SuperSoft\Plugin\System\TagRouting\Tag\ContentTypeScope::normalize($types);
            $assert($model->getState('params')->get('supjx_content_types') === $scope, 'View scope metadata missing.');
            $assert($model->getState('params')->get('supjx_single_content_type') === (count($scope) === 1), 'Single-type presentation depends on results instead of configuration.');
            $assert(($model->getState('tag.typesr') ?? []) === $scope, 'Native SQL type scope not set for items/counts/feeds.');
            $assert($app->params->get('supjx_content_types') === null, 'Scope leaked to shared params.');
        }
    }
    echo "OK ({$assertions} assertions; standalone menu page-size contracts)\n";
}
