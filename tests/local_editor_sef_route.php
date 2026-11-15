<?php
declare(strict_types=1);
// Run via tests/run_local_tag_query_filters.py tests/local_editor_sef_route.php
ob_start();
define('_JEXEC',1);define('JPATH_BASE','/var/www/html');
$_SERVER['HTTP_HOST']='localhost:8089';$_SERVER['REQUEST_URI']='/tag/thematic.html?task=tag.edit';
$_SERVER['SCRIPT_NAME']='/index.php';$_SERVER['REQUEST_METHOD']='GET';
parse_str(parse_url($_SERVER['REQUEST_URI'],PHP_URL_QUERY),$_GET);$_REQUEST=$_GET;
require JPATH_BASE.'/includes/defines.php';require JPATH_BASE.'/includes/framework.php';
spl_autoload_register(static function($class) use($sources) {if(isset($sources[$class])) {eval(substr(base64_decode($sources[$class]),5));}},true,true);
set_exception_handler(static function($error) {fwrite(STDERR,$error->getMessage()."\n".$error->getTraceAsString());exit(1);});
$c=\Joomla\CMS\Factory::getContainer();
$c->alias('session.web','session.web.site')->alias('session','session.web.site')->alias(\Joomla\Session\SessionInterface::class,'session.web.site');
// CLI derives Uri::base from the request path, unlike the web SAPI.
$c->get('config')->set('live_site','http://localhost:8089/');
$app=$c->get(\Joomla\CMS\Application\SiteApplication::class);\Joomla\CMS\Factory::$application=$app;
foreach(\Joomla\CMS\Factory::getConfig()->toArray() as $key=>$value) {$app->set($key,$value);}
$app->createExtensionNamespaceMap();(new ReflectionMethod($app,'initialise'))->invoke($app);$app->loadLanguage();$app->loadDocument();
\Joomla\CMS\Plugin\PluginHelper::importPlugin('system',null,true,$app->getDispatcher());
$app->getDispatcher()->dispatch('onAfterInitialise',new \Joomla\CMS\Event\Application\AfterInitialiseEvent('onAfterInitialise',['subject'=>$app]));
$db=$c->get(\Joomla\Database\DatabaseInterface::class);
$rootId=(int)$db->setQuery('SELECT user_id FROM #__user_usergroup_map WHERE group_id=8 ORDER BY user_id')->loadResult();
$app->loadIdentity(new \Joomla\CMS\User\User($rootId));$app->getSession()->set('user',$app->getIdentity());
register_shutdown_function(static function()use($app){if($app->getSession()->isActive()){$app->getSession()->set('user',new \Joomla\CMS\User\User());}});
// Checkouts during form display affect only this connection-local tag snapshot.
$table=$db->quoteName($db->getPrefix().'tags');
$tagsRows=$db->setQuery('SELECT * FROM '.$table)->loadObjectList();
$schema=$db->setQuery('SHOW CREATE TABLE '.$table)->loadRow();
$db->setQuery(preg_replace('/^CREATE TABLE /','CREATE TEMPORARY TABLE ',$schema[1]))->execute();
foreach($tagsRows as $row) {
    // Ignore live editing locks only in this temporary fixture snapshot.
    if(property_exists($row,'checked_out')) {$row->checked_out=null;$row->checked_out_time=null;}
    $db->insertObject('#__tags',$row);
}

$layoutPath=sys_get_temp_dir().'/smarttag-sef-'.bin2hex(random_bytes(8));mkdir($layoutPath,0700);file_put_contents($layoutPath.'/edit.php',$editorLayoutSource);$GLOBALS['smarttag_editor_layout_path']=$layoutPath;
register_shutdown_function(static function()use($layoutPath){unlink($layoutPath.'/edit.php');rmdir($layoutPath);});
$router=$c->get(\Joomla\CMS\Router\SiteRouter::class);
$parsed=$router->parse(new \Joomla\CMS\Uri\Uri('http://localhost:8089'.$_SERVER['REQUEST_URI']),true);
foreach($parsed as $key=>$value) {$app->getInput()->set($key,$value);}
define('JPATH_COMPONENT',JPATH_SITE.'/components/com_tags');define('JPATH_COMPONENT_ADMINISTRATOR',JPATH_ADMINISTRATOR.'/components/com_tags');
ob_start();$app->bootComponent('com_tags')->getDispatcher($app)->dispatch();$html=ob_get_clean();
if(!str_contains($html,'name="jform[title]"')) {throw new RuntimeException('Canonical SEF URL did not render frontend editor.');}
$id=(int)$db->setQuery("SELECT id FROM #__tags WHERE alias='thematic'")->loadResult();
if(!str_contains($html,'name="jform[id]" value="'.$id.'"')) {throw new RuntimeException('Canonical SEF URL selected wrong tag.');}
$built=$router->build('index.php?option=com_tags&view=tag&task=tag.edit&id='.$id.'&Itemid=0')->toString();
$roundTrip=$router->parse(new \Joomla\CMS\Uri\Uri('http://localhost:8089/'.ltrim($built,'/')),false);
if(empty($roundTrip['id']) || ($roundTrip['task']??'')!=='tag.edit') {throw new RuntimeException('New SEF edit link lost tag ID/task: '.$built);}
ob_end_clean();echo json_encode(['result'=>'PASS','canonical_edit_url'=>'frontend form rendered','new_edit_route'=>$built,'round_trip_id'=>$roundTrip['id'],'data'=>'connection-local temporary tag table'],JSON_PRETTY_PRINT),"\n";
