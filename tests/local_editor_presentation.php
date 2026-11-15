<?php
declare(strict_types=1);
// Run via tests/run_local_tag_query_filters.py tests/local_editor_presentation.php
ob_start();
define('_JEXEC',1);define('JPATH_BASE','/var/www/html');
$_SERVER['HTTP_HOST']='localhost:8089';$_SERVER['REQUEST_URI']='/';$_SERVER['SCRIPT_NAME']='/index.php';
require JPATH_BASE.'/includes/defines.php';require JPATH_BASE.'/includes/framework.php';
spl_autoload_register(static function($class)use($sources){if(isset($sources[$class])){eval(substr(base64_decode($sources[$class]),5));}},true,true);
set_exception_handler(static function($e){fwrite(STDERR,$e->getMessage()."\n".$e->getTraceAsString());exit(1);});
$c=\Joomla\CMS\Factory::getContainer();$c->alias('session.web','session.web.site')->alias('session','session.web.site')->alias(\Joomla\Session\SessionInterface::class,'session.web.site');
$c->get('config')->set('live_site','http://localhost:8089/');$app=$c->get(\Joomla\CMS\Application\SiteApplication::class);\Joomla\CMS\Factory::$application=$app;$app->createExtensionNamespaceMap();$app->loadLanguage();$app->loadDocument();$app->loadIdentity(new \Joomla\CMS\User\User());
$checks=0;$assert=static function($value,$message)use(&$checks){++$checks;if(!$value){throw new RuntimeException($message);}};
$form=new \Joomla\CMS\Form\Form('smarttag.presentation',['control'=>'jform']);
$form->load('<form><field name="title" type="text" required="true" label="Title"/><field name="alias" type="text" label="Alias"/><field name="parent_id" type="text" label="Parent"/><field name="description" type="textarea" label="Description"/><field name="published" type="list" label="Status"><option value="1">Published</option><option value="0">Unpublished</option></field><field name="access" type="text" label="Access"/><field name="language" type="text" label="Language"/><field name="note" type="text" label="Note"/><fields name="com_fields"><fieldset name="fields-0" label="JGLOBAL_FIELDS"><field name="ungrouped" type="text" label="Ungrouped"/></fieldset><fieldset name="fields-7" label="Appearance demo"><field name="required_example" type="text" required="true" label="Required example"/><field name="repeated" type="text" label="Repeated"/></fieldset><fieldset name="empty" label="Empty"/><fieldset name="hidden-only" label="Hidden only"><field name="hidden_example" type="hidden" default="retained"/></fieldset><fieldset name="duplicates" label="Duplicates"><field name="repeated" type="text" label="Repeated"/></fieldset></fields></form>');
$data=['title'=>'Retained draft','alias'=>'draft','parent_id'=>'1','description'=>'Retained description','published'=>'1','access'=>'1','language'=>'*','note'=>'Retained note','com_fields'=>['ungrouped'=>'Ungrouped value','required_example'=>'Valid value','repeated'=>'Once']];$form->bind($data);
$presentation=\SuperSoft\Plugin\System\SmartTagEnhancements\EditorTabs::prepare($form);
$assert(count($presentation['tabs'])===4,'Unexpected empty/duplicate/hidden tab.');
$assert(count($presentation['hidden'])===1,'Hidden custom field lost.');
$assert(isset($presentation['tabs']['smarttag-custom-fields-0']),'Ungrouped fields tab missing.');
$assert($presentation['tabs']['smarttag-custom-fields-7']['label']==='Appearance demo','Custom group title was replaced.');
$assert(array_map(static fn($f)=>$f->fieldname,$presentation['tabs']['smarttag-details']['fields'])===['title','alias','parent_id','description'],'Details fields assigned incorrectly.');
$assert(array_map(static fn($f)=>$f->fieldname,$presentation['tabs']['smarttag-publishing']['fields'])===['published','access','language','note'],'Publishing fields assigned incorrectly.');
$bad=$data;$bad['com_fields']['required_example']='';
$assert($form->validate($form->filter($bad))===false,'Native validation accepted missing required custom field.');
$invalid=\SuperSoft\Plugin\System\SmartTagEnhancements\EditorTabs::firstInvalid($form,$presentation['tabs'],$bad);
$assert($invalid['tab']==='smarttag-custom-fields-7','Server error did not select custom group tab.');
$badTitle=$data;$badTitle['title']='';
$invalidTitle=\SuperSoft\Plugin\System\SmartTagEnhancements\EditorTabs::firstInvalid($form,$presentation['tabs'],$badTitle);
$assert($invalidTitle['tab']==='smarttag-details','Title error did not select Details.');
$layoutPath=sys_get_temp_dir().'/smarttag-presentation-'.bin2hex(random_bytes(8));mkdir($layoutPath,0700);file_put_contents($layoutPath.'/edit.php',$editorLayoutSource);register_shutdown_function(static function()use($layoutPath){unlink($layoutPath.'/edit.php');rmdir($layoutPath);});
$render=static function($failed=false)use($form,$presentation,$invalid,$layoutPath){$presentation=\SuperSoft\Plugin\System\SmartTagEnhancements\EditorTabs::prepare($form);return (new \Joomla\CMS\Layout\FileLayout('edit',$layoutPath))->render(['form'=>$form,'item'=>(object)['id'=>999999],'action'=>'#editor-fixture','return'=>'','tabs'=>$presentation['tabs'],'hiddenFields'=>$presentation['hidden'],'activeTab'=>$failed?$invalid['tab']:'smarttag-details','invalidField'=>$failed?$invalid['field']:'','validationFailed'=>$failed]);};
$fixtures=[];
foreach($editorLanguages as $lang=>$encoded){
 $ini=base64_decode($encoded);$strings=parse_ini_string($ini,false,INI_SCANNER_RAW);$languageFile=tempnam(sys_get_temp_dir(),'smarttag-ui-lang-');file_put_contents($languageFile,$ini);(new ReflectionMethod($app->getLanguage(),'loadLanguage'))->invoke($app->getLanguage(),$languageFile,'test-'.$lang);unlink($languageFile);
 $html=$render();$dom=new DOMDocument();@$dom->loadHTML('<?xml encoding="UTF-8">'.$html);$xpath=new DOMXPath($dom);
 foreach(['tag.save'=>'SAVE_CLOSE','tag.apply'=>'APPLY','tag.cancel'=>'CANCEL'] as $task=>$key){$button=$xpath->query('//button[@value="'.$task.'"]')->item(0);$assert(trim($button->textContent)===$strings['PLG_SYSTEM_SMARTTAGENHANCEMENTS_'.$key],'Wrong button translation for '.$lang.' '.$task);}
 $assert($xpath->query('//input[@name="jform[com_fields][repeated]"]')->length===1,'Custom field rendered twice.');
 $assert(!str_contains($html,'name="Hidden only"')&&!str_contains($html,'name="Duplicates"')&&!str_contains($html,'name="Empty"'),'Empty tab rendered.');
 if($lang==='el-GR'){$fixtures['normal']=$html;$form->bind($bad);$fixtures['invalid']=$render(true);$form->bind($data);}
}
$assert(str_contains($fixtures['invalid'],'id="smarttag-custom-fields-7" active'),'Server error tab is not active.');
$assert(!str_contains($fixtures['invalid'],' recall'),'Recall would override server error tab.');
$assert(str_contains($fixtures['invalid'],'Retained description')&&str_contains($fixtures['invalid'],'Ungrouped value'),'Validation redisplay lost entered values.');
foreach(['published','access','language','note'] as $field){$form->removeField($field);}
$limited=\SuperSoft\Plugin\System\SmartTagEnhancements\EditorTabs::prepare($form);$assert(!isset($limited['tabs']['smarttag-publishing']),'Empty publishing tab was rendered.');
\Joomla\CMS\HTML\HTMLHelper::_('behavior.formvalidator');
$app->getDocument()->addScriptOptions('system.paths',['root'=>'','rootFull'=>'http://localhost:8089/','base'=>'','baseFull'=>'http://localhost:8089/']);
$app->getDocument()->getWebAssetManager()->registerAndUseScript('plg_system_smarttagenhancements.editor','plg_system_smarttagenhancements/editor.js',['version'=>'auto'],['defer'=>true],['core','form.validate','webcomponent.joomla-tab']);
$head=$app->getDocument()->loadRenderer('head')->render('head');
$result=['result'=>'PASS','checks'=>$checks,'coverage'=>'Native tab grouping, ungrouped/hidden/duplicate fields, EN/EL button labels, native server validation and value retention.'];
if($exportEditorFixtures??false){$result['fixtures']=[];foreach($fixtures as $key=>$html){$result['fixtures'][$key]=base64_encode('<!doctype html><html lang="el"><head><meta charset="utf-8"><base href="http://localhost:8089/">'.$head.'</head><body><div id="system-message-container"></div>'.$html.'</body></html>');}}
ob_end_clean();echo json_encode($result,JSON_PRETTY_PRINT),"\n";
