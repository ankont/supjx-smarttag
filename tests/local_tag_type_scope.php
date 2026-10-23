<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit(1); }
// Real Joomla runtime/DB, fixture menus only in memory; no configuration/content writes.
define('_JEXEC',1); define('JPATH_BASE','/var/www/html');
$_SERVER['HTTP_HOST']='localhost:8089'; $_SERVER['REQUEST_URI']='/'; $_SERVER['SCRIPT_NAME']='/index.php';
require JPATH_BASE.'/includes/defines.php'; require JPATH_BASE.'/includes/framework.php';
$c=\Joomla\CMS\Factory::getContainer();
$c->alias('session.web','session.web.site')->alias('session','session.web.site')->alias(\Joomla\Session\SessionInterface::class,'session.web.site');
$app=$c->get(\Joomla\CMS\Application\SiteApplication::class); \Joomla\CMS\Factory::$application=$app;
$app->createExtensionNamespaceMap();
$app->loadIdentity(new \Joomla\CMS\User\User());
\Joomla\CMS\Plugin\PluginHelper::importPlugin('system');
(new ReflectionMethod($app,'initialiseApp'))->invoke($app);
$db=$c->get(\Joomla\Database\DatabaseInterface::class);

use Joomla\CMS\Router\Route;
use Joomla\Registry\Registry;
$menu=$app->getMenu(); $original=$menu->getItem(141);
$items=(new ReflectionProperty($menu,'items'))->getValue($menu);
foreach ([90141=>['article-tags',[1]],90142=>['mixed-tags',[1,5]],90143=>['category-tags',[5]]] as $id=>[$route,$types]) {
    $item=clone $original; $item->id=$id; $item->route=$route; $item->alias=$route;
    $params=clone $original->getParams(); $params->set('content_types',$types); $params->set('tag_page_limit',30);
    $item->setParams($params); $item->query=['option'=>'com_tagrouting','view'=>'tags'];
    $items[$id]=$item;
}
(new ReflectionProperty($menu,'items'))->setValue($menu,$items);
$tag=(int)$db->setQuery("SELECT id FROM #__tags WHERE alias='primary'")->loadResult();
$input=$app->getInput(); $input->set('option','com_tags'); $input->set('view','tag'); $input->set('id',[$tag]);
$factory=$app->bootComponent('com_tags')->getMVCFactory();

if (in_array('--unbound-marker', $argv, true)) {
    $original->getParams()->set('content_types',[1]);
    $menu->setActive(141);
    $parse = new \SuperSoft\Plugin\System\TagRouting\Routing\StandaloneTagParseRule(
        $app,
        new \SuperSoft\Plugin\System\TagRouting\Tag\TagHierarchyRepository($db,new \SuperSoft\Plugin\System\TagRouting\Tag\TagHierarchyResolver()),
        new \SuperSoft\Plugin\System\TagRouting\Tag\StandaloneMenuContextResolver($menu),
        new \SuperSoft\Plugin\System\TagRouting\Routing\FacetDelimiter(),
    );
    $uri=new \Joomla\CMS\Uri\Uri('http://localhost:8089/!/age-group/primary');
    $router=$app->getRouter(); $parse($router,$uri);
    foreach($uri->getQuery(true) as $key=>$value){$input->set($key,$value);}
    $model=$factory->createModel('Tag','Site',['ignore_request'=>false]); $model->setCurrentUser($app->getIdentity());
    if ($model->getState('tag.typesr')) {throw new RuntimeException('Unbound marker inherited scoped home menu.');}
    $url=html_entity_decode(\SuperSoft\Plugin\System\TagRouting\Routing\TagRoute::forTag($tag));
    if (!str_contains($url,'/!/age-group/primary')) {throw new RuntimeException('Unbound marker links inherited scoped home menu: '.$url);}
    echo json_encode(['result'=>'PASS','scenario'=>'unbound marker with scoped active/home context','total'=>$model->getTotal(),'url'=>$url],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),"\n";
    exit;
}
$reports=[];
foreach ([141,90141,90142,90143] as $id) {
    $menu->setActive($id); $input->set('Itemid',$id);
    $input->set('format','html'); $input->set('types',[]); $input->set('limitstart',0);
    $input->get->set('limit',0); $input->set('limit',0);
    $model=$factory->createModel('Tag','Site',['ignore_request'=>false]);
    $model->setCurrentUser($app->getIdentity());
    $total=$model->getTotal(); $rows=$model->getItems();
    $keys=array_map(fn($i)=>$i->type_alias.':'.$i->content_item_id,$rows);
    $types=array_values(array_unique(array_column($rows,'type_alias')));
    $scope=$model->getState('params')->get('supjx_content_types');
    if ($id===141) { $baseline=$keys; $baselineRows=$rows; }
    $allowed=match($id){90141=>['com_content.article'],90143=>['com_content.category'], default=>['com_content.article','com_content.category']};
    if ($id!==141) {
        $expected=array_values(array_map(fn($i)=>$i->type_alias.':'.$i->content_item_id,array_filter($baselineRows,fn($i)=>in_array($i->type_alias,$allowed,true))));
        if ($keys!==$expected || $total!==count($expected)) { throw new RuntimeException('Type scope changed results/order/count.'); }
    }
    $url=html_entity_decode(Route::_('index.php?'.http_build_query(['option'=>'com_tags','view'=>'tag','id'=>[$tag],'Itemid'=>$id]),false));
    $route=$menu->getItem($id)->route;
    if (!str_contains($url,'/'.$route.'/age-group/primary') || str_contains($url,'__tagrouting')) {throw new RuntimeException('Real route builder lost namespace id='.$id.' expected='.$route.' scope='.json_encode($scope).' url='.$url);}
    $input->get->set('limit',30); $input->set('limit',30); $input->set('limitstart',30);
    // A contradictory public query must not widen a restricted namespace.
    $input->set('types',$id===90141?[5]:[]);
    $page=$factory->createModel('Tag','Site',['ignore_request'=>false]); $page->setCurrentUser($app->getIdentity());
    $pageKeys=array_map(fn($i)=>$i->type_alias.':'.$i->content_item_id,$page->getItems());
    if ($pageKeys!==array_slice($keys,$page->getStart(),30)) {throw new RuntimeException('Page membership/offset mismatch id='.$id);}
    $app->getRouter()->setVars(['option'=>'com_tags','view'=>'tag','id'=>[$tag],'Itemid'=>$id], false);
    $current=\Joomla\CMS\Uri\Uri::getInstance();
    $current->setPath(parse_url($url,PHP_URL_PATH)); $current->setQuery(['limit'=>30,'start'=>30]);
    $links=$page->getPagination()->getPagesLinks();
    if (str_contains($links,'__tagrouting') || ($total>30 && !str_contains($links,'/'.$route.'/age-group/primary'))) {throw new RuntimeException('Real pagination lost namespace id='.$id);}
    $input->set('format','feed'); $input->set('limitstart',0); $input->set('types',[]);
    $feed=$factory->createModel('Tag','Site',['ignore_request'=>false]); $feed->setCurrentUser($app->getIdentity());
    if ($scope!==[] && $feed->getState('tag.typesr')!==$scope) {throw new RuntimeException('Feed scope lost.');}
    $feedKeys=array_map(fn($i)=>$i->type_alias.':'.$i->content_item_id,$feed->getItems());
    if ($feedKeys!==array_slice($keys,0,$feed->getState('list.limit'))) {throw new RuntimeException('Feed membership differs from scoped results.');}
    $reports[]=['item'=>$id,'scope'=>$scope,'total'=>$total,'types'=>$types,'second_page_items'=>count($pageKeys),'url'=>$url,'feed_limit'=>$feed->getState('list.limit'),'single'=>$model->getState('params')->get('supjx_single_content_type'),'model'=>get_class($model)];
}
echo json_encode(['runtime'=>'Joomla '.JVERSION.' / PHP '.PHP_VERSION,'result'=>'PASS','menus'=>'in-memory only; no DB writes','reports'=>$reports],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),"\n";
