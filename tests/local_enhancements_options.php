<?php
declare(strict_types=1);
// Run via tests/run_local_tag_query_filters.py tests/local_enhancements_options.php
define('_JEXEC',1); define('JPATH_BASE','/var/www/html/administrator');
$_SERVER['HTTP_HOST']='localhost:8089'; $_SERVER['REQUEST_URI']='/administrator/'; $_SERVER['SCRIPT_NAME']='/administrator/index.php';
require JPATH_BASE.'/includes/defines.php'; require JPATH_BASE.'/includes/framework.php';
spl_autoload_register(static function($class) use($sources) { if(isset($sources[$class])) { eval(substr(base64_decode($sources[$class]),5)); } },true,true);
$c=\Joomla\CMS\Factory::getContainer();
$c->alias('session.web','session.web.administrator')->alias('session','session.web.administrator')->alias(\Joomla\Session\SessionInterface::class,'session.web.administrator');
$app=$c->get(\Joomla\CMS\Application\AdministratorApplication::class); \Joomla\CMS\Factory::$application=$app;
$app->createExtensionNamespaceMap();
$app->loadDocument();
$app->loadLanguage();
$db=$c->get(\Joomla\Database\DatabaseInterface::class);
$userId=(int)$db->setQuery('SELECT user_id FROM #__user_usergroup_map WHERE group_id=8 ORDER BY user_id')->loadResult();
if(!$userId) { throw new RuntimeException('Missing local administrator fixture.'); }
$app->loadIdentity(new \Joomla\CMS\User\User($userId));
$app->getInput()->set('component','com_tags');

set_exception_handler(static function($error){fwrite(STDERR,$error->getMessage()."\n".$error->getTraceAsString());exit(1);});
$checks=0;$assert=static function($ok,$message)use(&$checks){++$checks;if(!$ok){throw new RuntimeException($message);}};
$plugin=new \SuperSoft\Plugin\System\SmartTagEnhancements\Extension\SmartTagEnhancements($app->getDispatcher(),['name'=>'smarttagenhancements','type'=>'system']);$plugin->setApplication($app);
$record=\Joomla\CMS\Plugin\PluginHelper::getPlugin('system','smarttagenhancements');$originalParams=$record->params;
$record->params=json_encode(['enable_frontend_editing'=>0,'enable_custom_fields'=>1,'retained'=>'keep']);
$form=new \Joomla\CMS\Form\Form('com_config.component',['control'=>'jform']);
$form->load('<form><fieldset name="native"><field name="native_setting" type="text" /></fieldset></form>');
$plugin->onContentPrepareForm(new \Joomla\Event\Event('onContentPrepareForm',['form'=>$form,'data'=>new stdClass()]));
$assert($form->getField('native_setting')&&$form->getField('enhancements_enable_custom_fields'),'Options tab lost native/enhancement fields.');
$assert((int)$form->getValue('enhancements_enable_frontend_editing')===0,'Options tab did not load plugin values.');
$assert(str_contains($form->getField('enhancements_enable_custom_fields')->input,'class="switcher'),'Options tab does not use native Joomla switcher.');
$manifest=simplexml_load_file($GLOBALS['enhancements_manifest_fixture']);
$ownForm=new \Joomla\CMS\Form\Form('com_plugins.plugin');$ownForm->load('<form>'.$manifest->config->fields->asXML().'</form>');
$assert($ownForm->getField('enable_frontend_editing','params')&&$ownForm->getField('enable_custom_fields','params'),'Plugin configuration lacks switches.');
$ownFiltered=$ownForm->filter(['params'=>['enable_frontend_editing'=>'0','enable_custom_fields'=>'1']]);
$assert($ownFiltered['params']['enable_frontend_editing']===0&&$ownFiltered['params']['enable_custom_fields']===1,'Native plugin form did not filter switches.');
$app->loadIdentity(new \Joomla\CMS\User\User());
$hidden=new \Joomla\CMS\Form\Form('com_config.component');$hidden->load('<form><field name="native_setting" type="text" /></form>');
$plugin->onContentPrepareForm(new \Joomla\Event\Event('onContentPrepareForm',['form'=>$hidden,'data'=>new stdClass()]));
$assert(!$hidden->getField('enhancements_enable_custom_fields')&&$hidden->getField('native_setting'),'Restricted user saw editable plugin options.');
$unauthorised=(object)['type'=>'component','element'=>'com_tags','params'=>'{"enhancements_enable_custom_fields":0}'];
try{$plugin->onExtensionBeforeSave(new \Joomla\Event\Event('onExtensionBeforeSave',['context'=>'com_config.component','subject'=>$unauthorised]));$assert(false,'Unauthorised Save accepted.');}
catch(RuntimeException $error){$assert($error->getCode()===403,'Unauthorised Save did not reject with 403.');}
$app->loadIdentity(new \Joomla\CMS\User\User($userId));
// A connection-local extensions snapshot allows actual native table writes/rollbacks.
$rows=$db->setQuery("SELECT * FROM #__extensions WHERE (type='plugin' AND folder='system' AND element IN ('smarttagenhancements','tagrouting')) OR (type='component' AND element='com_tags')")->loadObjectList();
$schema=$db->setQuery('SHOW CREATE TABLE #__extensions')->loadRow();$db->setQuery(preg_replace('/^CREATE TABLE /','CREATE TEMPORARY TABLE ',$schema[1]))->execute();foreach($rows as $row){$db->insertObject('#__extensions',$row);}
$tags=new \Joomla\CMS\Table\Extension($db);$tags->load(['type'=>'component','element'=>'com_tags']);$oldTags=$tags->params;
$read=static fn($element)=>$db->setQuery("SELECT params FROM #__extensions WHERE type='plugin' AND folder='system' AND element=".$db->quote($element))->loadResult();
$db->setQuery("UPDATE #__extensions SET params=".$db->quote($record->params)." WHERE element='smarttagenhancements'")->execute();
$before=new \Joomla\Event\Event('onExtensionBeforeSave',['context'=>'com_config.component','subject'=>$tags]);
$after=new \Joomla\Event\Event('onExtensionAfterSave',['context'=>'com_config.component','subject'=>$tags]);
foreach([[0,0],[0,1],[1,0],[1,1]] as [$editing,$fields]){
 $params=new \Joomla\Registry\Registry($tags->params);$params->set('enhancements_enable_frontend_editing',$editing);$params->set('enhancements_enable_custom_fields',$fields);$tags->params=$params->toString();
 $plugin->onExtensionBeforeSave($before);$assert($tags->store(),'Native Tags settings store failed.');$plugin->onExtensionAfterSave($after);
 $saved=new \Joomla\Registry\Registry($read('smarttagenhancements'));
 $assert($saved->get('enable_frontend_editing')===$editing&&$saved->get('enable_custom_fields')===$fields,'Tags Save lost a feature selection.');
 $assert($saved->get('retained')==='keep','Tags Save discarded unrelated plugin values.');
 $tags->load($tags->extension_id);$assert($tags->params===$oldTags,'Enhancements settings leaked into com_tags params.');
}
$coordinator=new \SuperSoft\Plugin\System\SmartTagEnhancements\Configuration\OptionsSave($db);
$baseline=$read('smarttagenhancements');$params=new \Joomla\Registry\Registry($tags->params);$params->set('enhancements_enable_custom_fields',0);$tags->params=$params->toString();
$coordinator->before('com_config.component',$tags);$tags->store();$coordinator->abort();
$assert($read('smarttagenhancements')===$baseline,'Aborted Tags Save left plugin changes.');$tags->load($tags->extension_id);$assert($tags->params===$oldTags,'Aborted Tags Save left component changes.');
// Routing and Enhancements participate in one Save using their existing separate records.
$routing=new \SuperSoft\Plugin\System\TagRouting\Extension\TagRouting($app->getDispatcher(),['name'=>'tagrouting','type'=>'system']);$routing->setApplication($app);
$params=new \Joomla\Registry\Registry($tags->params);$params->set('smarttag_facet_include_descendants',1);$params->set('enhancements_enable_frontend_editing',0);$tags->params=$params->toString();
$routing->onExtensionBeforeSave($before);$plugin->onExtensionBeforeSave($before);$assert($tags->store(),'Combined Tags save failed.');$routing->onExtensionAfterSave($after);$plugin->onExtensionAfterSave($after);
$assert((new \Joomla\Registry\Registry($read('tagrouting')))->get('facet_include_descendants')===1,'Combined save lost Routing setting.');
$assert((new \Joomla\Registry\Registry($read('smarttagenhancements')))->get('enable_frontend_editing')===0,'Combined save lost Enhancements setting.');
$tags->load($tags->extension_id);$assert($tags->params===$oldTags,'Combined settings leaked into Tags.');
$record->params=$originalParams;
echo json_encode(['result'=>'PASS','checks'=>$checks,'coverage'=>'Plugin/Tags options, native switchers, plugin ACL, four switch combinations, separate storage, rollback and combined Routing+Enhancements Save. Temporary extension rows only.'],JSON_PRETTY_PRINT),"\n";
