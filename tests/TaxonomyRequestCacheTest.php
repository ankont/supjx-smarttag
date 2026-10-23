<?php
declare(strict_types=1);

namespace Joomla\Database {
    interface DatabaseInterface {}
    class ParameterType { public const INTEGER = 1; public const STRING = 2; }
}
namespace {
    require __DIR__ . '/run.php';
    use Joomla\Database\DatabaseInterface;
    use SuperSoft\Plugin\System\TagRouting\Infrastructure\CategoryTaxonomyResolver;
    final class ScopeQuery {
        public bool $root = false;
        public array $filters = [];
        public function select(...$args) { return $this; }
        public function from(...$args) { return $this; }
        public function innerJoin(...$args) { $this->root = true; return $this; }
        public function where(...$args) { return $this; }
        public function whereIn($column, $values, ...$args) { $this->filters[$column] = $values; return $this; }
        public function order(...$args) { return $this; }
        public function bind(...$args) { return $this; }
    }
    final class ScopeDatabase implements DatabaseInterface {
        public int $queries = 0;
        private ScopeQuery $query;
        public function createQuery() { return new ScopeQuery(); }
        public function quoteName($value, ...$args) { return $value; }
        public function quote($value) { return "'$value'"; }
        public function setQuery($query) { $this->query = $query; }
        public function loadObjectList() {
            ++$this->queries;
            $rows = $this->query->root
                ? [(object) ['id'=>1,'alias'=>'root','parent_id'=>0,'lft'=>1,'rgt'=>20,'title'=>'root','access'=>1,'language'=>'*','published'=>1]]
                : [
                    (object) ['id'=>2,'alias'=>'public','parent_id'=>1,'lft'=>2,'rgt'=>3,'title'=>'public','access'=>1,'language'=>'*','published'=>1],
                    (object) ['id'=>3,'alias'=>'private','parent_id'=>1,'lft'=>4,'rgt'=>5,'title'=>'private','access'=>2,'language'=>'*','published'=>1],
                    (object) ['id'=>4,'alias'=>'greek','parent_id'=>1,'lft'=>6,'rgt'=>7,'title'=>'greek','access'=>1,'language'=>'el-GR','published'=>1],
                    (object) ['id'=>5,'alias'=>'english','parent_id'=>1,'lft'=>8,'rgt'=>9,'title'=>'english','access'=>1,'language'=>'en-GB','published'=>1],
                    (object) ['id'=>6,'alias'=>'unpublished','parent_id'=>1,'lft'=>10,'rgt'=>11,'title'=>'hidden','access'=>1,'language'=>'*','published'=>0],
                ];
            return array_values(array_filter($rows, fn($r) => $r->published === 1 && in_array($r->access, $this->query->filters['t.access'], true) && in_array($r->language, $this->query->filters['t.language'], true)));
        }
    }
    $app = new \stdClass();
    $baselineDb = new ScopeDatabase();
    $baseline = [];
    for ($i=0; $i<3; ++$i) { $baseline[] = (new CategoryTaxonomyResolver($baselineDb))->resolve(9, [1], 'el-GR'); }
    $db = new ScopeDatabase();
    for ($i=0; $i<3; ++$i) {
        $scope = CategoryTaxonomyResolver::forApplication($app, $db)->resolve(9, [1], 'el-GR');
        $assert($scope == $baseline[$i], 'Shared cache changed scope results.');
    }
    $assert($baselineDb->queries === 6 && $db->queries === 2, 'Three package consumers did not share the two scope queries.');
    $ids = static fn($scope) => array_map(static fn($n) => $n->id, $scope['nodes']);
    $assert($ids($scope) === [1,2,4], 'Guest Greek scope fixture leaked ACL/language/unpublished nodes.');
    $private = CategoryTaxonomyResolver::forApplication($app, $db)->resolve(9, [1,2], 'el-GR');
    $assert($ids($private) === [1,2,3,4] && $db->queries === 4, 'ACL scopes shared a cache entry.');
    $english = CategoryTaxonomyResolver::forApplication($app, $db)->resolve(9, [1], 'en-GB');
    $assert($ids($english) === [1,2,5] && $db->queries === 6, 'Language scopes shared a cache entry.');
    CategoryTaxonomyResolver::forApplication($app, $db)->resolve(9, [2,1,1], 'el-GR');
    $assert($db->queries === 6, 'Equivalent ACL lists were not normalized.');
    CategoryTaxonomyResolver::forApplication($app, $db)->resolve(10, [1], 'el-GR');
    $assert($db->queries === 8, 'Categories shared a cache entry.');
    CategoryTaxonomyResolver::forApplication(new \stdClass(), $db)->resolve(9, [1], 'el-GR');
    $assert($db->queries === 10, 'Applications shared a request cache.');
    $secondDb = new ScopeDatabase();
    CategoryTaxonomyResolver::forApplication($app, $secondDb)->resolve(9, [1], 'el-GR');
    $assert($secondDb->queries === 2, 'Database instances shared a cache entry.');
    CategoryTaxonomyResolver::resetForApplication($app);
    CategoryTaxonomyResolver::forApplication($app, $db)->resolve(9, [1], 'el-GR');
    $assert($db->queries === 12, 'Request reset did not invalidate cached scope.');
    echo "OK ({$assertions} assertions; query-count and request/ACL/language cache isolation fixtures)\n";
}
