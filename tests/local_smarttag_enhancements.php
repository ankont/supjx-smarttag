<?php
declare(strict_types=1);
ob_start();
// Run via tests/run_local_tag_query_filters.py tests/local_smarttag_enhancements.php
define('_JEXEC',1); define('JPATH_BASE','/var/www/html/administrator');
$_SERVER['HTTP_HOST']='localhost:8089'; $_SERVER['REQUEST_URI']='/administrator/'; $_SERVER['SCRIPT_NAME']='/administrator/index.php';
require JPATH_BASE.'/includes/defines.php'; require JPATH_BASE.'/includes/framework.php';
define('JPATH_COMPONENT',JPATH_ADMINISTRATOR.'/components/com_fields');
define('JPATH_COMPONENT_ADMINISTRATOR',JPATH_COMPONENT);
\Joomla\CMS\Form\Form::addFormPath(JPATH_ADMINISTRATOR.'/components/com_tags/forms');
spl_autoload_register(static function($class) use($sources) {if(isset($sources[$class])) {eval(substr(base64_decode($sources[$class]),5));}},true,true);
$c=\Joomla\CMS\Factory::getContainer();
$c->alias('session.web','session.web.administrator')->alias('session','session.web.administrator')->alias(\Joomla\Session\SessionInterface::class,'session.web.administrator');
$app=$c->get(\Joomla\CMS\Application\AdministratorApplication::class); \Joomla\CMS\Factory::$application=$app;
$app->createExtensionNamespaceMap(); $app->loadLanguage(); $app->loadDocument();
$db=$c->get(\Joomla\Database\DatabaseInterface::class);
$rootId=(int)$db->setQuery('SELECT user_id FROM #__user_usergroup_map WHERE group_id=8 ORDER BY user_id')->loadResult();
if(!$rootId) {throw new RuntimeException('Missing root user fixture.');}
$app->loadIdentity(new \Joomla\CMS\User\User($rootId));
$app->getSession()->set('user',$app->getIdentity());
register_shutdown_function(static function() use($app) {if($app->getSession()->isActive()) {$app->getSession()->set('user',new \Joomla\CMS\User\User());}});
set_exception_handler(static function(\Throwable $error) {fwrite(STDERR,get_class($error).': '.$error->getMessage().' line '.$error->getLine()."\n".$error->getTraceAsString()."\n");exit(1);});
$checks=0;
$assert=static function(bool $ok,string $message) use(&$checks) {++$checks;if(!$ok) {throw new RuntimeException($message);}};
$shadow=static function(string $table,bool $copy=false,string $where='') use($db) {
    $rows=$copy?$db->setQuery('SELECT * FROM #__'.$table.($where?' WHERE '.$where:''))->loadAssocList():[];
    $schema=$db->setQuery('SHOW CREATE TABLE #__'.$table)->loadRow();
    $db->setQuery(preg_replace('/^CREATE TABLE /','CREATE TEMPORARY TABLE ',$schema[1]))->execute();
    foreach(array_chunk($rows,100) as $chunk) {
        $query=$db->createQuery()->insert('#__'.$table)->columns(array_map($db->quoteName(...),array_keys($chunk[0])));
        foreach($chunk as $row) {$query->values(implode(',',array_map(static fn($value)=>$value===null?'NULL':$db->quote($value),array_values($row))));}
        $db->setQuery($query)->execute();
    }
};
foreach(['fields','fields_groups','fields_values','fields_categories','contentitem_tag_map','ucm_content','ucm_base','history'] as $table) {
    $exists=$db->setQuery('SHOW TABLES LIKE '.$db->quote($db->getPrefix().$table))->loadResult();
    if($exists) {$shadow($table);}
}
$shadow('assets',true,"name='root.1' OR name='com_tags' OR name='com_content' OR name LIKE 'com_tags.%'");
$shadow('tags',true);
$plugin=new \SuperSoft\Plugin\System\SmartTagEnhancements\Extension\SmartTagEnhancements($app->getDispatcher(),['name'=>'smarttagenhancements','type'=>'system']);
$plugin->setApplication($app); $app->getDispatcher()->addSubscriber($plugin);
\Joomla\CMS\Plugin\PluginHelper::importPlugin('system','fields',true,$app->getDispatcher());
\Joomla\CMS\Plugin\PluginHelper::importPlugin('fields',null,true,$app->getDispatcher());
$input=$app->getInput(); $input->set('option','com_fields'); $input->set('context','com_tags.tag');
$component=$app->bootComponent('com_tags');
$assert($component instanceof \Joomla\CMS\Fields\FieldsServiceInterface,'Tags FieldsService registration missing.');
$assert(array_keys($component->getContexts())===['com_tags.tag'] && $component->validateSection('article')===null,'Context leaked to articles.');
\Joomla\CMS\Component\ComponentHelper::getParams('com_tags')->set('save_history',0);
$factory=$app->bootComponent('com_fields')->getMVCFactory();
$group=$factory->createModel('Group','Administrator',['ignore_request'=>false]); $group->setCurrentUser($app->getIdentity());
$assert($group->save(['id'=>0,'title'=>'Tag fixture group','description'=>'','note'=>'','context'=>'com_tags.tag','state'=>1,'access'=>1,'language'=>'*','params'=>[],'rules'=>[]]),'Native group save failed: '.$group->getError());
$groupId=(int)$group->getState('group.id');
if(!$groupId) {$groupId=(int)$db->setQuery("SELECT id FROM #__fields_groups WHERE context='com_tags.tag'")->loadResult();}
$definitions=[];
foreach(['text','textarea','integer','list','checkboxes','subform'] as $type) {
    $field=$factory->createModel('Field','Administrator',['ignore_request'=>false]); $field->setCurrentUser($app->getIdentity());
    $params=in_array($type,['list','checkboxes'],true)?['options'=>[['name'=>'Blue','value'=>'blue'],['name'=>'Red','value'=>'red']],'multiple'=>1]:[];
    if($type==='subform') {$params=['repeat'=>1,'options'=>[['customfield'=>$definitions['text'],'render_values'=>1]]];}
    $data=['id'=>0,'title'=>'Fixture '.$type,'label'=>'Fixture '.$type,'name'=>'fixture-'.$type,'type'=>$type,
        'context'=>'com_tags.tag','group_id'=>$groupId,'state'=>1,'access'=>1,'language'=>'*','required'=>$type==='text'?1:0,
        'default_value'=>'','description'=>'','note'=>'','fieldparams'=>$params,'params'=>['show_on'=>0,'display'=>0],'rules'=>[]];
    $assert($field->save($data),'Native field definition save failed: '.$type.' '.$field->getError());
    $id=(int)$field->getState('field.id');
    if(!$id) {$id=(int)$db->setQuery('SELECT id FROM #__fields WHERE name='.$db->quote('fixture-'.$type))->loadResult();}
    $definitions[$type]=$id;
    $form=$field->getForm(['id'=>$id,'context'=>'com_tags.tag','type'=>$type],false);
    $assert(count($form->getXml()->xpath('//field[@name="rules"]/action[@name="core.edit.value"]'))===1,'Field value ACL action missing.');
}
// Native list preferences must not leak between article fields and tag fields.
$articleFilters = ['group_id'=>999999, 'search'=>'article-only fixture'];
$app->setUserState('com_fields.fields.filter', $articleFilters);
$app->setUserState('com_fields.fields.list', ['limit'=>7]);
$app->setUserState('com_fields.groups.filter', ['search'=>'article-only group']);
$app->setUserState('com_fields.groups.list', ['limit'=>9]);
$input->set('view','fields');
$tagList=$factory->createModel('Fields','Administrator',['ignore_request'=>false]);
$tagList->setCurrentUser($app->getIdentity());
$assert(count($tagList->getItems())===count($definitions),'Article group/search filters hid tag fields.');
$assert($tagList->getState('filter.group_id')===null,'Tag list inherited article group filter.');
$assert($app->getUserState('com_fields.fields.filter')===$articleFilters,'Tag list overwrote article filters.');
$assert((bool)$tagList->getFilterForm(),'Scoped tag field filter form missing.');
$input->set('filter',['search'=>'fixture-text','group_id'=>$groupId]);
$input->set('list',['limit'=>2]);
$selectedTagList=$factory->createModel('Fields','Administrator',['ignore_request'=>false]);
$selectedTagList->getState();
$input->set('filter',null);$input->set('list',null);
$input->set('context','com_content.article');
$articleList=$factory->createModel('Fields','Administrator',['ignore_request'=>false]);
$assert($articleList->getState('filter.group_id')===999999,'Article group filter was lost.');
$assert($articleList->getState('list.limit')===7,'Tag page size changed article page size.');
$input->set('context','com_tags.tag');
$restoredTagList=$factory->createModel('Fields','Administrator',['ignore_request'=>false]);
$assert($restoredTagList->getState('filter.search')==='fixture-text','Tag search was not retained.');
$assert((int)$restoredTagList->getState('filter.group_id')===$groupId,'Tag group filter was not retained.');
$assert($restoredTagList->getState('list.limit')===2,'Tag page size was not retained.');
$input->set('view','groups');
$tagGroups=$factory->createModel('Groups','Administrator',['ignore_request'=>false]);
$tagGroups->setCurrentUser($app->getIdentity());
$assert(count($tagGroups->getItems())===1,'Article group search hid tag groups.');
$assert((bool)$tagGroups->getFilterForm(),'Scoped tag group filter form missing.');
$input->set('filter',['search'=>'Tag fixture']);$input->set('list',['limit'=>3]);
$selectedTagGroups=$factory->createModel('Groups','Administrator',['ignore_request'=>false]);$selectedTagGroups->getState();
$input->set('filter',null);$input->set('list',null);$input->set('context','com_content.article');
$articleGroups=$factory->createModel('Groups','Administrator',['ignore_request'=>false]);
$assert($articleGroups->getState('filter.search')==='article-only group','Tag groups overwrote article group search.');
$assert($articleGroups->getState('list.limit')===9,'Tag groups changed article group page size.');
$input->set('context','com_tags.tag');
$restoredGroups=$factory->createModel('Groups','Administrator',['ignore_request'=>false]);
$assert($restoredGroups->getState('filter.search')==='Tag fixture','Tag group search was not retained.');
$assert($restoredGroups->getState('list.limit')===3,'Tag group page size was not retained.');
// Clear only fixture-local preferences before the rest of the lifecycle checks.
foreach (['com_fields.fields.com_tags.tag','com_fields.groups.com_tags.tag'] as $listContext) {
    $app->setUserState($listContext.'.filter',[]);$app->setUserState($listContext.'.list',[]);
}
$input->set('view','fields');
$tagFactory=$component->getMVCFactory();
$model=$tagFactory->createModel('Tag','Administrator',['ignore_request'=>false]); $model->setCurrentUser($app->getIdentity());
$assert($model instanceof \SuperSoft\Plugin\System\SmartTagEnhancements\Administrator\Model\TagModel,'Admin model overlay missing.');
$controller=$tagFactory->createController('Tag','Administrator',['base_path'=>JPATH_ADMINISTRATOR.'/components/com_tags'],$app,$input);
$assert($controller->getModel('Tag','Administrator',['ignore_request'=>true]) instanceof \SuperSoft\Plugin\System\SmartTagEnhancements\Administrator\Model\TagModel,'Native controller did not use tag-field model.');
$input->set('option','com_tags');$input->set('view','tag');$input->set('task','apply');$input->set('id',0);
$data=['id'=>0,'parent_id'=>1,'title'=>'SmartTag field fixture','alias'=>'smarttag-field-fixture','published'=>1,'access'=>1,'language'=>'*','description'=>'',
    'params'=>[],'metadata'=>[],'images'=>[], 'com_fields'=>['fixture-text'=>'Example','fixture-textarea'=>'Line 1' . "\n" . 'Line 2','fixture-integer'=>'7','fixture-list'=>['blue'],'fixture-checkboxes'=>['blue','red'],'fixture-subform'=>['row0'=>['field'.$definitions['text']=>'Nested value']]]];
$assert($model->save($data),'Native tag save failed: '.$model->getError());
$tagId=(int)$model->getState('tag.id'); $assert($tagId>1,'New tag ID missing.');
$input->set('id',$tagId);
$form=$model->getForm(['id'=>$tagId,'language'=>'*'],false);
foreach($definitions as $type=>$id) {$assert((bool)$form->getField('fixture-'.$type,'com_fields'),'Native tag editor lacks field '.$type);}
$fields=\SuperSoft\Plugin\System\SmartTagEnhancements\TagFields::byName($tagId,false);
$assert(($fields['fixture-text']->rawvalue??null)==='Example','Text value not stored against tag.');
$assert((array)($fields['fixture-checkboxes']->rawvalue??null)===['blue','red'],'Multiple values lost.');
$prepared=\SuperSoft\Plugin\System\SmartTagEnhancements\TagFields::byName($tagId,true);
$assert(isset($prepared['fixture-list']->value),'Native prepared field values missing.');
$rawSubform=$factory->createModel('Field','Administrator',['ignore_request'=>true])->getFieldValue($definitions['subform'],$tagId);
$assert(json_decode((string)$rawSubform,true)===['row0'=>['field'.$definitions['text']=>'Nested value']],'Structured subform value lost native JSON representation.');
$valid=$form->filter($data);
$assert($form->validate($valid),'Native tag form validation failed.');
$invalid=$valid; $invalid['com_fields']['fixture-text']='';
$assert(!$form->validate($invalid),'Required field validation was bypassed.');
$article=(object)['id'=>$tagId,'type_alias'=>'com_content.article','jcfields'=>['unchanged']];
\SuperSoft\Plugin\System\SmartTagEnhancements\TagFields::attach($article);
$assert($article->jcfields===['unchanged'],'Tag fields replaced article fields.');
$assert(\SuperSoft\Plugin\System\SmartTagEnhancements\TagFields::get($article)===[],'Article item accepted as tag metadata.');
$data['id']=$tagId;$data['com_fields']['fixture-text']='Changed';$data['com_fields']['fixture-checkboxes']=false;
$assert($model->save($data),'Tag update failed.');
$fieldModel=$factory->createModel('Field','Administrator',['ignore_request'=>true]);
$assert($fieldModel->getFieldValue($definitions['text'],$tagId)==='Changed','Field update not persisted.');
$assert($fieldModel->getFieldValue($definitions['checkboxes'],$tagId)===null,'Cleared multi-value field was not deleted.');
$input->set('task','save2copy');$input->set('id',$tagId);$copy=$tagFactory->createModel('Tag','Administrator',['ignore_request'=>false]);$copy->setCurrentUser($app->getIdentity());
$copyData=$data;unset($copyData['com_fields']);$copyData['id']=0;
$assert($copy->save($copyData),'Save as Copy failed.');
$copyId=(int)$copy->getState('tag.id');
$assert($copyId!==$tagId && $fieldModel->getFieldValue($definitions['text'],$copyId)==='Changed','Save as Copy did not retain fields.');
$input->set('task','apply');
$batch=$tagFactory->createModel('Tag','Administrator',['ignore_request'=>false]);$batch->setCurrentUser($app->getIdentity());
$beforeBatch=(int)$db->setQuery('SELECT MAX(id) FROM #__tags')->loadResult();
$assert($batch->batch(['tag_id'=>1,'move_copy'=>'c'],[$copyId],[$copyId=>'com_tags.tag']),'Native public batch copy failed.');
$batchId=(int)$db->setQuery('SELECT MAX(id) FROM #__tags')->loadResult();
$assert($batchId>$beforeBatch,'Native batch copy did not create a tag.');
$assert($fieldModel->getFieldValue($definitions['text'],$batchId)==='Changed','Batch copy lost field values.');
$assert($fieldModel->getFieldValue($definitions['subform'],$batchId)===$rawSubform,'Batch copy changed structured field values.');
$input->set('option','com_fields');$input->set('context','com_content.article');$input->set('id',0);
$articleField=$factory->createModel('Field','Administrator',['ignore_request'=>false]);$articleField->setCurrentUser($app->getIdentity());
$assert($articleField->save(['id'=>0,'title'=>'Article-only fixture','label'=>'Article-only fixture','name'=>'fixture-article-only','type'=>'text',
    'context'=>'com_content.article','group_id'=>0,'state'=>1,'access'=>1,'language'=>'*','required'=>0,'description'=>'','note'=>'','default_value'=>'',
    'fieldparams'=>[],'params'=>['show_on'=>0,'display'=>0],'rules'=>[]]),'Article isolation fixture failed.');
$articleFieldId=(int)$db->setQuery("SELECT id FROM #__fields WHERE name='fixture-article-only' AND context='com_content.article'")->loadResult();
$assert($fieldModel->setFieldValue($articleFieldId,$tagId,'Article survives tag deletion'),'Article isolation value not saved.');
$input->set('option','com_tags');$input->set('context','com_tags.tag');
$db->setQuery('UPDATE #__tags SET published=0 WHERE id='.$tagId)->execute();
$app->loadIdentity(new \Joomla\CMS\User\User());
$app->getSession()->set('user',$app->getIdentity());
$assert(\SuperSoft\Plugin\System\SmartTagEnhancements\TagFields::get($tagId)===[],'Unpublished tag fields exposed to guest.');
$db->setQuery('UPDATE #__tags SET published=1,access=9999 WHERE id='.$tagId)->execute();
$assert(\SuperSoft\Plugin\System\SmartTagEnhancements\TagFields::get($tagId)===[],'Restricted tag fields exposed to guest.');
$assert(!$fieldModel->setFieldValue($definitions['text'],$copyId,'Forbidden'),'Guest bypassed native field edit ACL.');
$app->loadIdentity(new \Joomla\CMS\User\User($rootId));
$app->getSession()->set('user',$app->getIdentity());
$db->setQuery('UPDATE #__tags SET published=-2,access=1 WHERE id='.$tagId)->execute();
$delete=$tagFactory->createModel('Tag','Administrator',['ignore_request'=>false]);$delete->setCurrentUser($app->getIdentity());$pks=[$tagId];
$deleteFeatureRecord=\Joomla\CMS\Plugin\PluginHelper::getPlugin('system','smarttagenhancements');
$deleteOriginalParams=$deleteFeatureRecord->params;
$deleteFeatureRecord->params='{"enable_custom_fields":0}';
$assert($delete->delete($pks),'Native tag deletion failed.');
$deleteFeatureRecord->params=$deleteOriginalParams;
$assert((int)$db->setQuery("SELECT COUNT(*) FROM #__fields_values AS v INNER JOIN #__fields AS f ON f.id=v.field_id WHERE f.context='com_tags.tag' AND v.item_id=".$tagId)->loadResult()===0,'Deleted tag left orphan field values.');
$assert($fieldModel->getFieldValue($articleFieldId,$tagId)==='Article survives tag deletion','Deleting a tag deleted article fields with the same item ID.');
$assert($fieldModel->getFieldValue($definitions['text'],$copyId)==='Changed','Deleting source changed copy fields.');

// Management entry points and explicit permissions are native, scoped to tags.
$input->set('option','com_tags');$input->set('view','tags');
// Exercise the real menu pipeline with every installed system integration enabled.
foreach (\Joomla\CMS\Plugin\PluginHelper::getPlugin('system') as $systemPlugin) {
    if (!in_array($systemPlugin->name, ['tagcustomfields','smarttagenhancements'], true)) {
        \Joomla\CMS\Plugin\PluginHelper::importPlugin('system',$systemPlugin->name,true,$app->getDispatcher());
    }
}
require_once JPATH_ADMINISTRATOR.'/modules/mod_menu/src/Menu/CssMenu.php';
$nativeTagsId = (int) $db->setQuery("SELECT id FROM #__menu WHERE client_id=1 AND menutype='main' AND link='index.php?option=com_tags&view=tags'")->loadResult();
$assert($nativeTagsId > 0, 'Native Tags menu fixture missing.');
$cssMenu = new \Joomla\Module\Menu\Administrator\Menu\CssMenu($app, $db);
$nativeMenu = $cssMenu->load(new \Joomla\Registry\Registry(['menutype'=>'*','preset'=>'default']), true);
$nativeTags = null;
foreach ($nativeMenu->getChildren(true) as $nativeItem) {
    if ((int) $nativeItem->id === $nativeTagsId) {
        $nativeTags = $nativeItem;
    }
}
$assert($nativeTags !== null, 'Tags menu disappeared during preprocessing.');
$assert(!$nativeTags->hasChildren(), 'Tags must remain a clickable leaf.');
$nativeGroup = $nativeTags->getParent();
$assert($nativeGroup->title === 'SupJx SmartTags', 'SmartTag parent menu missing.');
$assert(count($nativeGroup->getChildren())===3, 'SmartTag parent must contain Tags, Fields and Groups.');
$assert(str_contains($nativeTags->link, 'adapter=tags') || str_contains($nativeTags->link, 'option=com_tags'), 'Tags manager destination was lost.');
ob_start();
$cssMenu->renderSubmenu(JPATH_ADMINISTRATOR.'/modules/mod_menu/tmpl/default_submenu.php', $nativeMenu);
$menuHtml = ob_get_clean();
$assert(str_contains($menuHtml, 'option=com_fields&view=fields&context=com_tags.tag'), 'Rendered menu lost Fields link.');
$assert(str_contains($menuHtml, 'option=com_fields&view=groups&context=com_tags.tag'), 'Rendered menu lost Field Groups link.');
$tagsMenu = new \Joomla\CMS\Menu\AdministratorMenuItem(['id'=>123,'link'=>'index.php?option=com_tags']);
$menuEvent = new \Joomla\CMS\Event\Menu\PreprocessMenuItemsEvent('onPreprocessMenuItems', [
    'context'=>'com_menus.administrator.module', 'subject'=>[$tagsMenu],
]);
$plugin->onPreprocessMenuItems($menuEvent);
$menuGroup = $menuEvent->getItems()[0];
$assert(count($menuGroup->getChildren())===3,'Tags/Fields/Groups submenu entries missing.');
$assert(!$tagsMenu->hasChildren(), 'Native Tags leaf acquired children.');
$assert($tagsMenu->link==='index.php?option=com_tags', 'Native Tags link changed.');
$plugin->onPreprocessMenuItems($menuEvent);
$assert(count($menuEvent->getItems())===1 && count($menuGroup->getChildren())===3,'Repeated preprocessing duplicates parent menu.');
$childEvent = new \Joomla\CMS\Event\Menu\PreprocessMenuItemsEvent('onPreprocessMenuItems', [
    'context'=>'com_menus.administrator.module', 'subject'=>$menuGroup->getChildren(),
]);
$plugin->onPreprocessMenuItems($childEvent);
$assert(count($menuGroup->getChildren())===3 && !$tagsMenu->hasChildren(), 'Recursive preprocessing nested the Tags menu.');
foreach (array_slice($menuGroup->getChildren(),1) as $child) {
    $assert(str_contains($child->link,'context=com_tags.tag'),'Submenu lost tag context.');
}

$permissions=new \Joomla\CMS\Form\Form('com_config.component');
$permissions->load('<form><field name="rules" type="rules" component="com_tags" section="component" /></form>');
$input->set('component','com_tags');
$plugin->onContentPrepareForm(new \Joomla\Event\Event('onContentPrepareForm',['form'=>$permissions,'data'=>new stdClass()]));
$assert(count($permissions->getXml()->xpath('//field[@name="rules"]/action[@name="core.edit.value"]'))===1,'Tags global edit-value permission missing.');
$groupForm=$group->getForm(['context'=>'com_tags.tag','id'=>$groupId],false);
$assert(count($groupForm->getXml()->xpath('//field[@name="rules"]/action[@name="core.edit.value"]'))===1,'Field-group edit-value permission missing.');

$menuFeatureRecord=\Joomla\CMS\Plugin\PluginHelper::getPlugin('system','smarttagenhancements');$menuOriginalParams=$menuFeatureRecord->params;
$menuFeatureRecord->params='{"enable_custom_fields":0}';
$plainTags=new \Joomla\CMS\Menu\AdministratorMenuItem(['id'=>456,'link'=>'index.php?option=com_tags&view=tags']);
$disabledMenuEvent=new \Joomla\CMS\Event\Menu\PreprocessMenuItemsEvent('onPreprocessMenuItems',['context'=>'com_menus.administrator.module','subject'=>[$plainTags]]);
$plugin->onPreprocessMenuItems($disabledMenuEvent);
$assert($disabledMenuEvent->getItems()[0]===$plainTags&&!$plainTags->hasChildren(),'Disabled custom fields still added management menus.');
$disabledModel=$tagFactory->createModel('Tag','Administrator',['ignore_request'=>true]);$disabledModel->setCurrentUser($app->getIdentity());$disabledModel->setState('tag.id',$copyId);
$disabledForm=$disabledModel->getForm(['id'=>$copyId],false);
$assert(!$disabledForm->getField('fixture-text','com_fields'),'Disabled custom fields still appeared in admin tag editor.');
$menuFeatureRecord->params=$menuOriginalParams;

// A restricted field is hidden independently of the tag's own public access.
$input->set('option','com_fields');$input->set('view','fields');$input->set('context','com_tags.tag');
$secret=$factory->createModel('Field','Administrator',['ignore_request'=>false]);$secret->setCurrentUser($app->getIdentity());
$assert($secret->save(['id'=>0,'title'=>'Secret fixture','label'=>'Secret fixture','name'=>'fixture-secret','type'=>'text','context'=>'com_tags.tag',
    'group_id'=>$groupId,'state'=>1,'access'=>9999,'language'=>'*','required'=>0,'description'=>'','note'=>'','default_value'=>'',
    'fieldparams'=>[],'params'=>['show_on'=>0,'display'=>0],'rules'=>[]]),'Restricted field fixture save failed.');
$db->setQuery('UPDATE #__tags SET published=1,access=1 WHERE id='.$copyId)->execute();
$dispatcher=$app->getDispatcher();$dispatcher->removeSubscriber($plugin);
// Core uses require_once for provider.php: re-register its providers when this
// test deliberately boots the same component again in a second application.
$dispatcher->addListener('onBeforeExtensionBoot',static function($event) {
    if($event->getArgument('extensionName')!=='tags'||$event->getArgument('type')!==\Joomla\CMS\Extension\ComponentInterface::class) {return;}
    $container=$event->getArgument('container');
    $container->registerServiceProvider(new \Joomla\CMS\Extension\Service\Provider\MVCFactory('Joomla\\Component\\Tags'));
    $container->registerServiceProvider(new \Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory('Joomla\\Component\\Tags'));
    $container->registerServiceProvider(new \Joomla\CMS\Extension\Service\Provider\RouterFactory('Joomla\\Component\\Tags'));
});
$site=$c->get(\Joomla\CMS\Application\SiteApplication::class);\Joomla\CMS\Factory::$application=$site;
// The process started in administrator; frontend return URLs use the site root.
foreach(['base','root'] as $uriProperty) {(new ReflectionProperty(\Joomla\CMS\Uri\Uri::class,$uriProperty))->setValue(null,['prefix'=>'http://localhost:8089','path'=>'']);}
$site->createExtensionNamespaceMap();$site->loadLanguage();$site->loadDocument();
// JPATH_THEMES remains the administrator path in this multi-app CLI fixture.
$site->setTemplate('atum');$site->loadIdentity(new \Joomla\CMS\User\User());
$sitePlugin=new \SuperSoft\Plugin\System\SmartTagEnhancements\Extension\SmartTagEnhancements($dispatcher,['name'=>'smarttagenhancements','type'=>'system']);$sitePlugin->setApplication($site);
$dispatcher->addSubscriber($sitePlugin);
unset(\Joomla\CMS\Extension\ExtensionHelper::$extensions[\Joomla\CMS\Extension\ComponentInterface::class]['tags']);
foreach(['fieldsCache','fieldCache'] as $cache) {(new ReflectionProperty(\Joomla\Component\Fields\Administrator\Helper\FieldsHelper::class,$cache))->setValue(null,null);}
$public=\SuperSoft\Plugin\System\SmartTagEnhancements\TagFields::byName($copyId,false);
$assert(isset($public['fixture-text'])&&!isset($public['fixture-secret']),'Frontend field ACL isolation failed.');
$assert(($public['fixture-text']->rawvalue??null)==='Changed','Frontend tag field retrieval failed.');
$assert(str_contains(\SuperSoft\Plugin\System\SmartTagEnhancements\TagFields::render($copyId),'Changed'),'Frontend native field layout rendering failed.');
$enabled=new ReflectionProperty(\Joomla\CMS\Language\Multilanguage::class,'enabled');$savedEnabled=$enabled->getValue();$enabled->setValue(null,true);
$otherLanguage=$site->getLanguage()->getTag()==='fr-FR'?'de-DE':'fr-FR';
$db->setQuery('UPDATE #__tags SET language='.$db->quote($otherLanguage).' WHERE id='.$copyId)->execute();
$assert(\SuperSoft\Plugin\System\SmartTagEnhancements\TagFields::get($copyId)===[],'Other-language tag metadata exposed.');
$db->setQuery("UPDATE #__tags SET language='*' WHERE id=".$copyId)->execute();$enabled->setValue(null,$savedEnabled);
$nativeSiteModel=$site->bootComponent('com_tags')->getMVCFactory()->createModel('Tag','Site',['ignore_request'=>true]);
$assert(get_class($nativeSiteModel)===\Joomla\Component\Tags\Site\Model\TagModel::class,'Independent plugin replaced native site model.');

// Execute the frontend edit/save lifecycle through the decorated native factory.
$layoutPath = sys_get_temp_dir().'/smarttag-editor-'.bin2hex(random_bytes(8));
mkdir($layoutPath,0700);file_put_contents($layoutPath.'/edit.php',$editorLayoutSource);
$GLOBALS['smarttag_editor_layout_path']=$layoutPath;
register_shutdown_function(static function() use($layoutPath) {unlink($layoutPath.'/edit.php');rmdir($layoutPath);});
$siteInput=$site->getInput();$siteInput->set('option','com_tags');$siteInput->set('format','html');
$siteInput->set('id',[$copyId]);
$siteFactory=$site->bootComponent('com_tags')->getMVCFactory();
$expectStatus=static function(callable $operation,int $status,string $message) use($assert) {
    try {$operation();$assert(false,$message);} catch(\RuntimeException $error) {$assert($error->getCode()===$status,$message.' '.$error->getMessage());}
};
$makeEditor=static fn()=>$siteFactory->createController('Tag','Site',['base_path'=>JPATH_SITE.'/components/com_tags'],$site,$siteInput);
$expectStatus(static fn()=>$makeEditor()->execute('edit'),403,'Guest frontend tag edit allowed.');
$expectStatus(static fn()=>$makeEditor()->execute('delete'),404,'Unsupported tag task allowed.');
$site->loadIdentity(new \Joomla\CMS\User\User($rootId));$app->getSession()->set('user',$site->getIdentity());
$siteInput->set('return',base64_encode('http://localhost:8089/tag/return.html'));
$siteInput->set('id',null);$siteInput->set('view','thematic');
$expectStatus(static fn()=>$makeEditor()->execute('edit'),404,'Missing tag ID was guessed from an alias.');
$siteInput->set('view','tag');$siteInput->set('id',[$copyId]);
ob_start();$makeEditor()->execute('edit');$editorHtml=ob_get_clean();
$assert(str_contains($editorHtml,'name="jform[title]"'),'Frontend editor title input missing.');
$assert(str_contains($editorHtml,'fixture-text'),'Frontend editor omitted native custom fields.');
$assert(str_contains($editorHtml,'Changed'),'Frontend editor did not load stored field value.');
$assert(str_contains($editorHtml,'tag.cancel'),'Frontend editor cancel control missing.');
$siteInput->server->set('REQUEST_METHOD','POST');
$expectStatus(static fn()=>$makeEditor()->execute('save'),403,'Frontend save accepted missing CSRF token.');
$token=\Joomla\CMS\Session\Session::getFormToken();$siteInput->post->set($token,1);
$siteInput->set('id',$copyId);
$siteInput->post->set('jform',['id'=>$copyId+1,'title'=>'Tampered','parent_id'=>1]);
$expectStatus(static fn()=>$makeEditor()->execute('save'),403,'Frontend save accepted mismatched tag ID.');
$siteInput->post->set('jform',['id'=>$copyId,'title'=>'','parent_id'=>1,'com_fields'=>['fixture-text'=>'Field value']]);
ob_start();$makeEditor()->execute('save');$invalidHtml=ob_get_clean();
$assert(str_contains($invalidHtml,'name="jform[title]"'),'Validation failure did not redisplay form.');
$assert($db->setQuery('SELECT title FROM #__tags WHERE id='.$copyId)->loadResult()!=='','Invalid frontend form was saved.');
$siteInput->post->set('jform',['id'=>$copyId,'title'=>'Retained valid title','parent_id'=>1,'description'=>'Retained after group validation','com_fields'=>['fixture-text'=>'']]);
ob_start();$makeEditor()->execute('save');$groupInvalidHtml=ob_get_clean();
$assert(str_contains($groupInvalidHtml,'id="smarttag-custom-fields-'.$groupId.'" active'),'Native custom field validation did not reveal its group tab.');
$assert(str_contains($groupInvalidHtml,'Retained valid title')&&str_contains($groupInvalidHtml,'Retained after group validation'),'Native validation redisplay lost posted values.');

$siteInput->post->set('jform',['id'=>$copyId,'title'=>'Frontend edited tag','alias'=>'frontend-edited-tag','parent_id'=>1,
    'com_fields'=>['fixture-text'=>'Frontend value','fixture-checkboxes'=>[],'fixture-subform'=>['row0'=>['field'.$definitions['text']=>'Frontend nested']]]]);
$editor=$makeEditor();$editor->execute('save');
$assert((new ReflectionProperty(\Joomla\CMS\MVC\Controller\BaseController::class,'redirect'))->getValue($editor)==='http://localhost:8089/tag/return.html','Save & Close did not return to the permitted URL.');
$assert($db->setQuery('SELECT title FROM #__tags WHERE id='.$copyId)->loadResult()==='Frontend edited tag','Frontend save failed.');
$assert($db->setQuery('SELECT value FROM #__fields_values WHERE field_id='.$definitions['text'].' AND item_id='.$copyId)->loadResult()==='Frontend value','Frontend custom field save failed.');
$assert(!(int)$db->setQuery('SELECT COUNT(*) FROM #__fields_values WHERE field_id='.$definitions['checkboxes'].' AND item_id='.$copyId)->loadResult(),'Frontend multi-value clear failed.');
$returnClass=\SuperSoft\Plugin\System\SmartTagEnhancements\ReturnUrl::class;
$assert($returnClass::decode(base64_encode('https://evil.example/path'),'/safe')==='/safe','External return URL accepted.');
$assert($returnClass::decode(base64_encode('javascript:alert(1)'),'/safe')==='/safe','Unsafe return scheme accepted.');
$assert($returnClass::decode(base64_encode('http://localhost:8089/index.php?task=tag.save'),'/safe')==='/safe','Return URL task loop accepted.');
$siteInput->post->set('jform',['id'=>$copyId,'title'=>'Applied tag','alias'=>'applied-tag','parent_id'=>1,'com_fields'=>['fixture-text'=>'Applied value']]);
$editor=$makeEditor();$editor->execute('apply');
$redirect=(new ReflectionProperty(\Joomla\CMS\MVC\Controller\BaseController::class,'redirect'))->getValue($editor);
$assert(str_contains($redirect,'task=tag.edit'),'Apply did not return to editor.');
// An authenticated editor without state/value permissions cannot escalate through POST.
$limitedUser = new class($rootId) extends \Joomla\CMS\User\User {
    public function authorise($action, $assetname = null) {return $action === 'core.edit' && $assetname === 'com_tags';}
};
$site->loadIdentity($limitedUser);$app->getSession()->set('user',$limitedUser);
$stateBefore=$db->setQuery('SELECT published,access,language FROM #__tags WHERE id='.$copyId)->loadAssoc();
$valueBefore=$db->setQuery('SELECT value FROM #__fields_values WHERE field_id='.$definitions['text'].' AND item_id='.$copyId)->loadResult();
$siteInput->post->set('jform',['id'=>$copyId,'title'=>'Limited editor update','alias'=>'limited-editor-update','parent_id'=>1,
    'published'=>0,'access'=>999999,'language'=>'xx-XX','com_fields'=>['fixture-text'=>'Forbidden value']]);
$makeEditor()->execute('save');
$assert($db->setQuery('SELECT published,access,language FROM #__tags WHERE id='.$copyId)->loadAssoc()===$stateBefore,'Frontend editor escalated state/access/language.');
$assert($db->setQuery('SELECT value FROM #__fields_values WHERE field_id='.$definitions['text'].' AND item_id='.$copyId)->loadResult()===$valueBefore,'Frontend editor bypassed Edit Field Value permission.');
$site->loadIdentity(new \Joomla\CMS\User\User($rootId));$app->getSession()->set('user',$site->getIdentity());
$beforeCancel=$db->setQuery('SELECT title FROM #__tags WHERE id='.$copyId)->loadResult();
$siteInput->post->set('jform',['id'=>$copyId,'title'=>'Must not save']);$cancelEditor=$makeEditor();$cancelEditor->execute('cancel');
$assert((new ReflectionProperty(\Joomla\CMS\MVC\Controller\BaseController::class,'redirect'))->getValue($cancelEditor)==='http://localhost:8089/tag/return.html','Cancel did not return to the permitted URL.');
$assert($db->setQuery('SELECT title FROM #__tags WHERE id='.$copyId)->loadResult()===$beforeCancel,'Cancel saved changes.');
// Each feature switch gates its own normal entry points independently.
$featureRecord=\Joomla\CMS\Plugin\PluginHelper::getPlugin('system','smarttagenhancements');
$originalFeatureParams=$featureRecord->params;
$siteInput->server->set('REQUEST_METHOD','GET');
foreach ([[1,0],[0,1],[0,0],[1,1]] as [$editingEnabled,$fieldsEnabled]) {
    $featureRecord->params=json_encode(['enable_frontend_editing'=>$editingEnabled,'enable_custom_fields'=>$fieldsEnabled]);
    \Joomla\Component\Fields\Administrator\Helper\FieldsHelper::clearFieldsCache();
    $metadata=\SuperSoft\Plugin\System\SmartTagEnhancements\TagFields::get($copyId,false);
    $assert(($metadata!==[])===(bool)$fieldsEnabled,'Custom fields switch did not gate public API.');
    $nativeFields=\Joomla\Component\Fields\Administrator\Helper\FieldsHelper::getFields('com_tags.tag',(object)['id'=>$copyId]);
    $assert(($nativeFields!==[])===(bool)$fieldsEnabled,'Custom fields switch did not gate native field consumers.');
    if ($editingEnabled) {
        ob_start();$makeEditor()->execute('edit');$featureHtml=ob_get_clean();
        $assert(str_contains($featureHtml,'name="jform[title]"'),'Custom fields switch disabled frontend editing.');
        $assert(str_contains($featureHtml,'fixture-text')===(bool)$fieldsEnabled,'Custom fields switch did not gate frontend form.');
        if (!$fieldsEnabled) {
            $existingValue=$db->setQuery('SELECT value FROM #__fields_values WHERE field_id='.$definitions['text'].' AND item_id='.$copyId)->loadResult();
            $siteInput->server->set('REQUEST_METHOD','POST');
            $siteInput->post->set('jform',['id'=>$copyId,'title'=>'Editing without fields','alias'=>'editing-without-fields','parent_id'=>1,'com_fields'=>['fixture-text'=>'Must remain ignored']]);
            $makeEditor()->execute('save');
            $assert($db->setQuery('SELECT value FROM #__fields_values WHERE field_id='.$definitions['text'].' AND item_id='.$copyId)->loadResult()===$existingValue,'Disabled custom fields accepted forged values.');
            $siteInput->server->set('REQUEST_METHOD','GET');$siteInput->post->set('jform',[]);
        }

    } else {
        $expectStatus(static fn()=>$makeEditor()->execute('edit'),404,'Frontend editing switch did not reject editor entry.');
        $siteInput->server->set('REQUEST_METHOD','POST');
        $expectStatus(static fn()=>$makeEditor()->execute('save'),404,'Frontend editing switch did not reject POST save.');
        $siteInput->server->set('REQUEST_METHOD','GET');
    }
}
$featureRecord->params=$originalFeatureParams;
\Joomla\Component\Fields\Administrator\Helper\FieldsHelper::clearFieldsCache();
$siteInput->server->set('REQUEST_METHOD','GET');$siteInput->post->set('jform',[]);$siteInput->post->set($token,null);
$site->loadIdentity(new \Joomla\CMS\User\User());$app->getSession()->set('user',$site->getIdentity());

// Both load orders preserve the Routing plugin's final site model and field context.
$routingRecord=\Joomla\CMS\Plugin\PluginHelper::getPlugin('system','tagrouting');$oldRouting=$routingRecord->params;
$routingParams=new \Joomla\Registry\Registry($oldRouting);$routingParams->set('enable_hierarchical',1);$routingRecord->params=$routingParams->toString();
$routing=new \SuperSoft\Plugin\System\TagRouting\Extension\TagRouting($dispatcher,['name'=>'tagrouting','type'=>'system']);$routing->setApplication($site);
foreach([false,true] as $routingFirst) {
    $dispatcher->removeSubscriber($sitePlugin);$dispatcher->removeSubscriber($routing);
    foreach($routingFirst?[$routing,$sitePlugin]:[$sitePlugin,$routing] as $subscriber) {$dispatcher->addSubscriber($subscriber);}
    unset(\Joomla\CMS\Extension\ExtensionHelper::$extensions[\Joomla\CMS\Extension\ComponentInterface::class]['tags']);
    $combined=$site->bootComponent('com_tags');
    $assert($combined instanceof \Joomla\CMS\Fields\FieldsServiceInterface,'Routing removed tag Fields context.');
    $assert($combined->getMVCFactory()->createModel('Tag','Site',['ignore_request'=>true]) instanceof \SuperSoft\Plugin\System\TagRouting\Tags\Site\Model\TagModel,'Tag fields replaced routing site model.');
}
$routingRecord->params=$oldRouting;
$app->getSession()->set('user',new \Joomla\CMS\User\User());
ob_end_clean();
echo json_encode(['result'=>'PASS','checks'=>$checks,'field_types'=>array_keys($definitions),
    'coverage'=>'Native Fields/Groups models, tag editor/save/update/copy/delete, raw/prepared API and ACL. All mutable tables are connection-local temporary copies; no installed configuration/content changes.'],JSON_PRETTY_PRINT),"\n";
