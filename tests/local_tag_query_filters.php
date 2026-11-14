<?php
declare(strict_types=1);
// Run via tests/run_local_tag_query_filters.py. Real local Joomla/DB, no writes.
define('_JEXEC', 1); define('JPATH_BASE', '/var/www/html');
$_SERVER['HTTP_HOST']='localhost:8089'; $_SERVER['REQUEST_URI']='/'; $_SERVER['SCRIPT_NAME']='/index.php';
$_SERVER['REQUEST_METHOD']='GET';
require JPATH_BASE . '/includes/defines.php'; require JPATH_BASE . '/includes/framework.php';
spl_autoload_register(static function ($class) use ($sources) {
    if (isset($sources[$class])) { eval(substr(base64_decode($sources[$class]), 5)); }
}, true, true);
$c = \Joomla\CMS\Factory::getContainer();
$c->alias('session.web','session.web.site')->alias('session','session.web.site')->alias(\Joomla\Session\SessionInterface::class,'session.web.site');
$app = $c->get(\Joomla\CMS\Application\SiteApplication::class); \Joomla\CMS\Factory::$application=$app;
$app->createExtensionNamespaceMap(); $app->loadIdentity(new \Joomla\CMS\User\User());
\Joomla\CMS\Plugin\PluginHelper::importPlugin('system');
(new ReflectionMethod($app,'initialiseApp'))->invoke($app);

$db=$c->get(\Joomla\Database\DatabaseInterface::class);
$tag=(int)$db->setQuery("SELECT id FROM #__tags WHERE alias='primary' ORDER BY id")->loadResult();
if (!$tag) { throw new RuntimeException('Missing local primary tag fixture.'); }
$input=$app->getInput(); $input->set('option','com_tags'); $input->set('view','tag'); $input->set('id',[$tag]);
$input->set('limit',0); $input->get->set('limit',0);
$factory=$app->bootComponent('com_tags')->getMVCFactory();
$model = static function(array $filters=[], int $start=0, int $limit=0) use ($app,$input,$factory) {
    foreach (['content-types','content-category'] as $key) { $input->get->set($key,$filters[$key]??null); }
    $input->set('limit',$limit); $input->get->set('limit',$limit); $input->set('limitstart',$start);
    $m=new \SuperSoft\Plugin\System\TagRouting\Tags\Site\Model\TagModel(['ignore_request'=>false],$factory);
    $m->setCurrentUser($app->getIdentity()); return $m;
};
$keys=static fn($rows)=>array_map(static fn($r)=>$r->type_alias.':'.$r->content_item_id,$rows);
$baseline=$model()->getItems(); $articleRows=array_values(array_filter($baseline,static fn($r)=>$r->type_alias==='com_content.article'));
if (!$articleRows) { throw new RuntimeException('Missing article fixture.'); }
$category=(int)$db->setQuery('SELECT catid FROM #__content WHERE id='.(int)$articleRows[0]->content_item_id)->loadResult();
$categoryArticles=array_map('intval',$db->setQuery('SELECT id FROM #__content WHERE catid='.$category)->loadColumn());
$checks=0;
foreach ([[],['content-types'=>'com_content.article'],['content-types'=>'com_content.category,com_content.article'],
    ['content-types'=>'com_content.article,com_content.category,com_content.article'],
    ['content-category'=>(string)$category],['content-category'=>(string)$category,'content-types'=>'com_content.category']] as $filters) {
    $aliases=isset($filters['content-types'])?explode(',',$filters['content-types']):[];
    $expected=array_values(array_filter($baseline,static fn($r)=>
        (!$aliases||in_array($r->type_alias,$aliases,true)) && (!isset($filters['content-category'])||
            ($r->type_alias==='com_content.article'&&in_array((int)$r->content_item_id,$categoryArticles,true)))));
    $m=$model($filters); $actual=$m->getItems();
    if ($keys($actual)!==$keys($expected)||$m->getTotal()!==count($expected)) { throw new RuntimeException('Result/total mismatch: '.json_encode($filters)); }
    foreach ([0,2] as $start) {
        $page=$model($filters,$start,2); $rows=$page->getItems();
        if ($keys($rows)!==array_slice($keys($expected),$page->getStart(),2)) { throw new RuntimeException('Pagination mismatch.'); }
        $page->getPagination();
        ++$checks;
    }
    $input->set('format','feed');
    $feed=$model($filters); $feedRows=$feed->getItems();
    if ($feed->getTotal()!==count($expected)||$keys($feedRows)!==array_slice($keys($expected),0,count($feedRows))) { throw new RuntimeException('Feed mismatch.'); }
    $input->set('format','html'); ++$checks;
}
foreach ([['content-types'=>'unknown.type'],['content-category'=>'999999999'],['content-types'=>[]]] as $invalid) {
    try { $model($invalid)->getItems(); throw new RuntimeException('Invalid filter accepted.'); }
    catch (\Joomla\CMS\Router\Exception\RouteNotFoundException) { ++$checks; }
}
// Builders preserve explicit public filters independently of native type scope.
$url=html_entity_decode(\Joomla\CMS\Router\Route::_('index.php?'.http_build_query([
    'option'=>'com_tags','view'=>'tag','id'=>[$tag],
    'content-types'=>'com_content.article,com_content.category', 'content-category'=>$category,
    'start'=>2,'cat-view'=>'details',
]),false));
parse_str(parse_url($url,PHP_URL_QUERY)??'', $query);
if (($query['content-types']??null)!=='com_content.article,com_content.category'
    || (int)($query['content-category']??0)!==$category || (int)($query['start']??0)!==2
    || ($query['cat-view']??null)!=='details' || str_contains($url,'__tagrouting')) {
    throw new RuntimeException('Route builder lost public filters or leaked internal state: '.$url);
}
++ $checks;

// Menu fixtures live only in this PHP process: no menu/configuration writes.
$menu=$app->getMenu(); $original=$menu->getItem(141);
if (!$original) { throw new RuntimeException('Missing local SmartTag menu fixture 141.'); }
$fixture=clone $original; $fixture->id=90141; $fixture->route='pages/nav/tags'; $fixture->alias='tags';
$fixture->query=['option'=>'com_tagrouting','view'=>'tags']; $fixture->component='com_tagrouting';
$params=clone $original->getParams(); $params->set('enable_tag_facets',1); $params->set('tag_facet_scope','all');
$params->set('use_menu_route_canonical',1); $params->set('group_article_categories',1); $params->set('content_types',[]);
$fixture->setParams($params);
$items=(new ReflectionProperty($menu,'items'))->getValue($menu); $items[90141]=$fixture;
(new ReflectionProperty($menu,'items'))->setValue($menu,$items); $menu->setActive(90141); $input->set('Itemid',90141);
$grouped=$model(); $groupedRows=$grouped->getItems();
$expected=array_values(array_filter($baseline,static fn($r)=>$r->type_alias!=='com_content.category'));
$actualKeys=$keys($groupedRows); $expectedKeys=$keys($expected); sort($actualKeys); sort($expectedKeys);
if ($actualKeys!==$expectedKeys || $grouped->getTotal()!==count($expected)) { throw new RuntimeException('Grouping changed content membership/count.'); }
$last=null; $seen=[];
foreach ($groupedRows as $row) {
    $key=$row->supjx_group_key;
    if ($key!==$last && isset($seen[$key])) { throw new RuntimeException('Group not contiguous.'); }
    $seen[$key]=true; $last=$key;
    if ($row->type_alias==='com_content.article' && !str_starts_with($key,'com_content.category:')) { throw new RuntimeException('Missing live category metadata.'); }
}
$page=$model([],2,3);
if ($keys($page->getItems())!==array_slice($keys($groupedRows),$page->getStart(),3)) { throw new RuntimeException('Grouped pagination unstable.'); }
if (count($grouped->getGroupedItems())!==count($seen)) { throw new RuntimeException('Grouped adapter mismatch.'); }
$checks+=4;

$language=$app->getLanguage()->getTag(); $levels=array_values($app->getIdentity()->getAuthorisedViewLevels());
$repository=new \SuperSoft\Plugin\System\TagRouting\Tag\TagHierarchyRepository($db,new \SuperSoft\Plugin\System\TagRouting\Tag\TagHierarchyResolver());
$menus=new \SuperSoft\Plugin\System\TagRouting\Tag\StandaloneMenuContextResolver($menu);
$markers=new \SuperSoft\Plugin\System\TagRouting\Routing\FacetDelimiter();
$router=$app->getRouter();
$router->attachBuildRule(new \SuperSoft\Plugin\System\TagRouting\Routing\StandaloneTagPrepareBuildRule($app),\Joomla\CMS\Router\SiteRouter::PROCESS_BEFORE);
$router->attachBuildRule(new \SuperSoft\Plugin\System\TagRouting\Routing\StandaloneTagBuildRule($app,$repository,$menus,$markers),\Joomla\CMS\Router\SiteRouter::PROCESS_DURING);
$parser=new \SuperSoft\Plugin\System\TagRouting\Routing\StandaloneTagParseRule($app,$repository,$menus,$markers);
$service=new \SuperSoft\Plugin\System\TagRouting\Tag\StandaloneFacetResolver($app,$db);
$scope=$service->scope(90141,$language);
$byId=[]; foreach ($scope['nodes'] as $node) { $byId[$node->id]=$node; }
$basePath=$repository->pathForId($tag,$levels,$language);
$base='pages/nav/tags/'.implode('/',$basePath->aliases());
$child=$byId[$tag]??null; $parent=$child===null?null:($byId[$child->parentId]??null);
if ($parent===null) { throw new RuntimeException('Missing parent/child tag fixture.'); }
$pathFor=static function($node) use($byId) {
    $path=[$node->alias]; while(isset($byId[$node->parentId])) { $node=$byId[$node->parentId]; array_unshift($path,$node->alias); }
    return implode('/',$path);
};
$expression=$pathFor($parent).'+'.$pathFor($child);
foreach ([0,1] as $descendants) {
    $params->set('tag_facet_descendants',$descendants); $fixture->setParams($params);
    $state=$service->resolve($tag,90141,$expression,$language);
    if (count($state['selected'])!==2) { throw new RuntimeException('Selected parent/child lost.'); }
    $uri=new \Joomla\CMS\Uri\Uri('http://localhost:8089/'.$base.'/!/'.$expression.'?start=2&cat-view=details');
    $parser($router,$uri); foreach($uri->getQuery(true) as $k=>$v) { $input->set($k,$v); }
    $input->set('id',[$tag]); $input->set('option','com_tags'); $input->set('view','tag');
    $validated=\SuperSoft\Plugin\System\TagRouting\Routing\TagFilterRequest::current();
    if ($validated===null) { throw new RuntimeException('Validated request state missing.'); }
    $filtered=$model(); $actual=$filtered->getItems();
    $maps=$db->setQuery('SELECT content_item_id,type_alias,tag_id FROM #__contentitem_tag_map WHERE tag_id IN ('.implode(',',array_unique(array_merge(...$state['groups']))).')')->loadObjectList();
    $membership=[]; foreach($maps as $m) { $membership[$m->type_alias.':'.$m->content_item_id][]=(int)$m->tag_id; }
    $expected=array_values(array_filter($groupedRows,static function($row) use($state,$membership) {
        $ids=$membership[$row->type_alias.':'.$row->content_item_id]??[];
        foreach($state['groups'] as $group) { if(array_intersect($group,$ids)===[]) {return false;} } return true;
    }));
    if ($keys($actual)!==$keys($expected)||$filtered->getTotal()!==count($expected)) { throw new RuntimeException('Extra filter query mismatch.'); }
    $url=html_entity_decode(\SuperSoft\Plugin\System\TagRouting\Routing\TagFilterRoute::get($tag,90141,$expression,['start'=>999,'cat-view'=>'details']));
    if (!str_contains($url,'/pages/nav/tags/')||!str_contains($url,'/!/')||str_contains($url,'__tagrouting')||str_contains($url,'start=')) {throw new RuntimeException('Extra filter builder mismatch: '.$url);}
    if ($descendants && $expected === []) { throw new RuntimeException('Descendant fixture must exercise nonempty results.'); }
    $router->setVars($uri->getQuery(true), false);
    $currentUri=\Joomla\CMS\Uri\Uri::getInstance(); $currentUri->setPath('/'.$base.'/!/'.$state['expression']); $currentUri->setQuery(['start'=>3]);
    $paged=$model([],3,3); $pageRows=$paged->getItems();
    if ($keys($pageRows)!==array_slice($keys($expected),$paged->getStart(),3)) { throw new RuntimeException('Extra-filter pagination membership mismatch.'); }
    $links=$paged->getPagination()->getPagesLinks();
    if (count($expected)>3 && (!str_contains($links,'/!/') || !str_contains($links,'start='))) { throw new RuntimeException('Extra-filter pagination lost path/offset: '.substr($links,0,2200)); }
    $input->set('format','feed'); $feed=$model();
    if ($feed->getTotal()!==count($expected) || $keys($feed->getItems())!==array_slice($keys($expected),0,count($feed->getItems()))) {throw new RuntimeException('Extra-filter feed mismatch.');}
    $input->set('format','html'); $checks+=3;
    if (str_contains($links,'__tagrouting')) { throw new RuntimeException('Internal state leaked into pagination.'); }
    (new ReflectionProperty($router,'tainted'))->setValue($router,false);
    $canonicalUri=new \Joomla\CMS\Uri\Uri('http://localhost:8089/'.$base.'/!/'.$state['expression'].'?start=2&cat-view=details');
    $parser($router,$canonicalUri);
    if ($router->isTainted()) { throw new RuntimeException('Canonical extra filter path would redirect again.'); }
    $rawParts=explode('+',$state['expression']);
    $raw=implode('+',array_reverse($rawParts)).'+'.$rawParts[0];
    $noncanonical=new \Joomla\CMS\Uri\Uri('http://localhost:8089/'.$base.'/!filter/'.$raw.'?start=2&cat-view=details');
    $parser($router,$noncanonical);
    if (!$router->isTainted()) { throw new RuntimeException('Noncanonical extra filters did not request Joomla SEF redirect.'); }
    $rebuilt=html_entity_decode(\Joomla\CMS\Router\Route::_('index.php?'.http_build_query($noncanonical->getQuery(true)),false));
    if (str_contains($rebuilt,'!filter') || str_contains($rebuilt,'__tagrouting') || !str_contains($rebuilt,'/!/'.$state['expression'])) { throw new RuntimeException('Noncanonical route did not rebuild directly to canonical: '.$rebuilt); }
    $checks+=3;
    $checks+=4;
}
foreach ([$base.'/!/unknown-filter-tag', $base.'/!/a++b', $base.'/!/'.implode('+',array_fill(0,11,$pathFor($child)))] as $invalidPath) {
    try { $uri=new \Joomla\CMS\Uri\Uri('http://localhost:8089/'.$invalidPath); $parser($router,$uri); throw new RuntimeException('Invalid tag facet route accepted: ' . $invalidPath . ''); }
    catch (\Joomla\CMS\Router\Exception\RouteNotFoundException) { ++$checks; }
}
$originalParams=clone $original->getParams(); $boundParams=clone $originalParams; $boundParams->set('bind_root_marker',1); $boundParams->set('content_types',[]); $original->setParams($boundParams);
$boundParser=new \SuperSoft\Plugin\System\TagRouting\Routing\StandaloneTagParseRule($app,$repository,new \SuperSoft\Plugin\System\TagRouting\Tag\StandaloneMenuContextResolver($menu),$markers);
try { $bad=new \Joomla\CMS\Uri\Uri('http://localhost:8089/!/'.implode('/',$basePath->aliases()).'/!/'.$pathFor($child)); $boundParser($router,$bad); throw new RuntimeException('Double marker accepted as standalone.'); }
catch (\Joomla\CMS\Router\Exception\RouteNotFoundException) { ++$checks; }
$original->setParams($originalParams);
\SuperSoft\Plugin\System\TagRouting\Routing\TagFilterRequest::reset($app);
$params->set('tag_facet_scope','selected'); $params->set('tag_facet_roots',[]); $fixture->setParams($params);
if (\SuperSoft\Plugin\System\TagRouting\Routing\TagFilterRoute::available(90141)['nodes']!==[]
    || \SuperSoft\Plugin\System\TagRouting\Routing\TagFilterRoute::get($tag,90141,$expression)!=='') { throw new RuntimeException('Empty roots widened scope.'); }
$params->set('tag_facet_roots',[$parent->id]); $fixture->setParams($params);
if (\SuperSoft\Plugin\System\TagRouting\Routing\TagFilterRoute::get($tag,90141,$expression)==='') { throw new RuntimeException('Selected root scope rejected its descendants.'); }
$params->set('enable_tag_facets',0); $fixture->setParams($params);
if (\SuperSoft\Plugin\System\TagRouting\Routing\TagFilterRoute::get($tag,90141,$expression)!=='') { throw new RuntimeException('Disabled facets built a link.'); }
$params->set('enable_tag_facets',1); $params->set('tag_facet_scope','all'); $fixture->setParams($params);
$checks+=3;
$params->set('use_menu_route_canonical',0); $fixture->setParams($params);
if (\SuperSoft\Plugin\System\TagRouting\Routing\TagFilterRoute::get($tag,90141,$expression)!=='') {throw new RuntimeException('Noncanonical namespace accepted.');}
++ $checks;

// Compare taxonomy modes against explicit live roots; no fixture data writes.
$own=(new \SuperSoft\Plugin\System\TagRouting\Infrastructure\CategoryTaxonomyResolver($db,'category'))->resolve($category,$levels,$language);
$inherited=(new \SuperSoft\Plugin\System\TagRouting\Infrastructure\CategoryTaxonomyResolver($db,'inherited'))->resolve($category,$levels,$language);
$all=(new \SuperSoft\Plugin\System\TagRouting\Infrastructure\CategoryTaxonomyResolver($db,'all'))->resolve($category,$levels,$language);
$nodeIds=static fn($s)=>array_map(static fn($n)=>$n->id,$s['nodes']);
if (array_diff($nodeIds($own),$nodeIds($inherited))||array_diff($nodeIds($inherited),$nodeIds($all))) { throw new RuntimeException('Taxonomy scopes are not additive.'); }
$checks+=2;

// External serialized state is never a public alternative to the path grammar.
\SuperSoft\Plugin\System\TagRouting\Routing\TagFilterRequest::reset($app);
$external=new \Joomla\CMS\Uri\Uri('http://localhost:8089/'.$base.'?'.http_build_query(['__tagrouting_extra'=>json_encode($state),'start'=>2]));
$boundary=new \SuperSoft\Plugin\System\TagRouting\Routing\InternalQueryBoundary($app);
$boundary->parse($router,$external);
if (array_key_exists('__tagrouting_extra',$external->getQuery(true)) || \SuperSoft\Plugin\System\TagRouting\Routing\TagFilterRequest::current()!==null) { throw new RuntimeException('External filter state was trusted.'); }
$unrelated=new \Joomla\CMS\Uri\Uri('http://localhost:8089/pages/nav/!/primary');
$parser($router,$unrelated);
if ($unrelated->getPath()!=='/pages/nav/!/primary') { throw new RuntimeException('Nested standalone parser intercepted category filtering.'); }
$checks+=2;

// Real com_content model: validate both matching modes against native visible rows.
if ($own['nodes']!==[]) {
    $input->set('option','com_content'); $input->set('view','category'); $input->set('id',$category);
    $contentFactory=$app->bootComponent('com_content')->getMVCFactory();
    $makeArticles=static function(bool $candidate) use($contentFactory,$app,$category) {
        $m=$candidate ? new \SuperSoft\Plugin\System\TagRouting\Site\Model\ArticlesModel(['ignore_request'=>true],$contentFactory)
            : new \Joomla\Component\Content\Site\Model\ArticlesModel(['ignore_request'=>true],$contentFactory);
        $m->setCurrentUser($app->getIdentity()); $m->getState();
        $m->setState('params', \Joomla\CMS\Component\ComponentHelper::getParams('com_content'));
        foreach(['filter.category_id'=>$category,'filter.subcategories'=>false,'filter.published'=>1,'filter.access'=>true,
            'list.limit'=>0,'list.start'=>0,'list.ordering'=>'a.id','list.direction'=>'ASC'] as $k=>$v) { $m->setState($k,$v); }
        return $m;
    };
    $nativeRows=$makeArticles(false)->getItems();
    $selected=isset($byId[$tag])&&in_array($tag,$nodeIds($own),true)?$byId[$tag]:$own['nodes'][0];
    $public=new \SuperSoft\Plugin\System\TagRouting\Domain\TagExpression([new \SuperSoft\Plugin\System\TagRouting\Domain\TagGroup([$selected])]);
    $serialized=(new \SuperSoft\Plugin\System\TagRouting\Domain\SerializedExpression())->encode($category,$public,$public);
    \SuperSoft\Plugin\System\TagRouting\Routing\FacetedRequest::remember($app,$serialized);
    $plugin=\Joomla\CMS\Plugin\PluginHelper::getPlugin('system','tagrouting'); $savedParams=$plugin->params;
    foreach([false,true] as $descendants) {
        $settings=new \Joomla\Registry\Registry($savedParams); $settings->set('facet_include_descendants',(int)$descendants); $settings->set('facet_scope','category'); $plugin->params=$settings->toString();
        \SuperSoft\Plugin\System\TagRouting\Infrastructure\CategoryTaxonomyResolver::resetForApplication($app);
        $groups=\SuperSoft\Plugin\System\TagRouting\Domain\FacetMembership::groups([[$selected->id]],$own['nodes'],$descendants);
        $matching=array_map('intval',$db->setQuery("SELECT DISTINCT content_item_id FROM #__contentitem_tag_map WHERE type_alias='com_content.article' AND tag_id IN (".implode(',',$groups[0]).')')->loadColumn());
        $expected=array_values(array_filter($nativeRows,static fn($row)=>in_array((int)$row->id,$matching,true)));
        $candidate=$makeArticles(true); $rows=$candidate->getItems();
        if(array_column($rows,'id')!==array_column($expected,'id') || $candidate->getTotal()!==count($expected)) {throw new RuntimeException('Category descendant matching changed native visibility/results.');}
        ++$checks;
    }
    $plugin->params=$savedParams; \SuperSoft\Plugin\System\TagRouting\Routing\FacetedRequest::reset($app);
}
echo json_encode(['result'=>'PASS','checks'=>$checks,'baseline'=>count($baseline),'category'=>$category,
    'coverage'=>'Real local Joomla model/database, anonymous ACL, totals/offsets/feeds; not frontend HTTP or persisted menu changes'],JSON_PRETTY_PRINT),"\n";
