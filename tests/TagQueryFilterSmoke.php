<?php
declare(strict_types=1);

namespace Joomla\CMS\Router\Exception { class RouteNotFoundException extends \RuntimeException {} }
namespace {
    require __DIR__ . '/run.php';
    use SuperSoft\Plugin\System\TagRouting\Tags\TagQueryFilter;

    $filter = TagQueryFilter::fromQuery(['content-types'=>'com_content.category,com_content.article,com_content.article', 'content-category'=>'9']);
    $assert($filter->parameters() === ['content-types'=>'com_content.article,com_content.category','content-category'=>9], 'Stable multiple types/category parsing.');
    $assert(TagQueryFilter::fromQuery([])->parameters() === [], 'Absent filters must be inert.');
    foreach ([['content-types'=>[]], ['content-types'=>''], ['content-types'=>'com_content.article,'],
        ['content-types'=>'com_content.article OR 1=1'], ['content-types'=>str_repeat('a',2049)],
        ['content-types'=>implode(',',array_fill(0,33,'com_content.article'))],
        ['content-category'=>'0'], ['content-category'=>'-1'], ['content-category'=>[]],
        ['content-category'=>'1.0'], ['content-category'=>'99999999999999999999999']] as $input) {
        try { TagQueryFilter::fromQuery($input); $assert(false, 'Invalid query accepted.'); }
        catch (\Joomla\CMS\Router\Exception\RouteNotFoundException) { $assert(true, 'Rejected.'); }
    }
    class Query {
        public array $conditions = [];
        public function select($v) { return $this; }
        public function from($v) { return $this; }
        public function where($v) { $this->conditions[] = $v; return $this; }
    }
    class Database {
        public array $results = [2,9];
        public array $lookups = [];
        public function createQuery() { return new Query(); }
        public function quote($v) { return "'" . str_replace("'", "''", $v) . "'"; }
        public function setQuery($query) { $this->lookups[] = $query; return $this; }
        public function loadResult() { return array_shift($this->results); }
    }
    $db = new Database(); $query = new Query();
    $filter->apply($query, $db, [1], 'el-GR');
    $assert(count($query->conditions) === 3, 'Both filters must intersect existing restrictions.');
    $assert(str_contains($query->conditions[0], "'com_content.article','com_content.category'"), 'Types must use OR via IN.');
    $assert(str_contains($query->conditions[2], 'catid = 9'), 'Category must use live article membership.');
    $categorySql = implode(' AND ', $db->lookups[1]->conditions);
    foreach (['c.published = 1','c.access IN (1)',"'el-GR'",'ancestor.published <> 1',"c.extension = 'com_content'"] as $guard) {
        $assert(str_contains($categorySql, $guard), 'Missing category visibility guard: ' . $guard);
    }
    foreach ([[1,9],[2,null]] as $results) {
        $db = new Database(); $db->results = $results;
        try { $filter->apply(new Query(), $db, [1], 'el-GR'); $assert(false, 'Unknown/invisible filter accepted.'); }
        catch (\Joomla\CMS\Router\Exception\RouteNotFoundException) { $assert(true, 'Rejected.'); }
    }
    echo "OK ({$assertions} assertions; public tag query filters)\n";
}
