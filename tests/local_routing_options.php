<?php
declare(strict_types=1);
// Run via tests/run_local_tag_query_filters.py tests/local_routing_options.php
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
$plugin=new \SuperSoft\Plugin\System\TagRouting\Extension\TagRouting($app->getDispatcher(),['name'=>'tagrouting','type'=>'system']); $plugin->setApplication($app);
$temp=tempnam(sys_get_temp_dir(),'smarttag-form-'); rename($temp,$temp.'.xml'); $temp.='.xml';
$GLOBALS['smarttag_form_fixture']=$temp; file_put_contents($temp,base64_decode($formSources[0]));
$langTemp=tempnam(sys_get_temp_dir(),'smarttag-lang-'); file_put_contents($langTemp,base64_decode($formSources[3]));
(new ReflectionMethod($app->getLanguage(),'loadLanguage'))->invoke($app->getLanguage(),$langTemp,'plg_system_tagrouting');
$checks=0;
try {
    $form=new \Joomla\CMS\Form\Form('com_config.component',['control'=>'jform']);
    $form->load('<form><fieldset name="native"><field name="tag_list_show_tag_image" type="radio" /></fieldset></form>');
    $plugin->onContentPrepareForm(new \Joomla\Event\Event('onContentPrepareForm',['form'=>$form,'data'=>new stdClass()]));
    if(!$form->getField('smarttag_facet_include_descendants')||!$form->getField('tag_list_show_tag_image')) {throw new RuntimeException('Tab insertion lost native fields.');}
    if($form->getXml()->xpath('//fieldset[@name="smarttag_routing"]/@description')) {throw new RuntimeException('Internal info banner still present.');}
    $overview=$form->getField('smarttag_routing_help')->renderField();
    if(!str_contains($overview,'alert alert-info')||str_contains($overview,'hide-aware-inline-help')
        ||!str_contains($overview,'#attrib-howto')||!str_contains($overview,'task=plugin.edit')||str_contains($overview,'extension_id=0')||str_contains($overview,"target='_blank'")) {throw new RuntimeException('Summary is not always visible or help link does not target the plugin help tab.');}
    ++$checks;
    $markers=$form->getField('smarttag_accepted_markers');
    if($markers->type!=='MarkerControls') {throw new RuntimeException('Compact marker controls not loaded.');}
    $html=$markers->input;
    if(substr_count($html,'class="switcher')!==4 || substr_count($html,'type="radio"')!==8 || !str_contains($html,'[custom_enabled]') || str_contains($html,'form-switch')) {throw new RuntimeException('Marker row rendering failed.');}
    if(str_contains($html,'type="text"')) {throw new RuntimeException('Custom textbox remains on the switch row.');}
    $condition=\Joomla\CMS\Form\FormHelper::parseShowOnConditions($form->getFieldAttribute('smarttag_custom_marker','showon'), 'jform');
    if($condition[0]['field']!=='jform[smarttag_accepted_markers][custom_enabled]'||$condition[0]['values']!==['1']
        ||!str_contains($form->getField('smarttag_custom_marker')->renderField(),'data-showon')) {throw new RuntimeException('Custom field is not a native separately aligned show-on row.');}
    ++$checks;
    $submitted=['smarttag_facet_include_descendants'=>'1','smarttag_accepted_markers'=>['short'=>'1','tag'=>'0','filter'=>'1','custom_enabled'=>'1'],'smarttag_custom_marker'=>'!topic','smarttag_breadcrumb_marker'=>''];
    $filtered=$form->filter($submitted);
    if(!$form->validate($filtered)) {throw new RuntimeException('Native form validation failed.');}
    if($filtered['smarttag_accepted_markers']!==['short'=>'1','tag'=>'0','filter'=>'1','custom_enabled'=>'1']||$filtered['smarttag_breadcrumb_marker']!=='') {throw new RuntimeException('Form filtering changed selections/empty marker: '.json_encode([$filtered['smarttag_accepted_markers']??null,$filtered['smarttag_breadcrumb_marker']??null]));}
    $checks+=4;
    $menu=new \Joomla\CMS\Form\Form('com_menus.item'); $menuXml=simplexml_load_string(base64_decode($formSources[1])); $menu->load('<form>'.$menuXml->fields->asXML().'</form>');
    $plugin->onContentPrepareForm(new \Joomla\Event\Event('onContentPrepareForm',['form'=>$menu,'data'=>new stdClass()]));
    $options=$menu->getXml()->xpath('//field[@name="tag_facet_descendants"]/option');
    if(count($options)!==3||strpos((string)$options[0],'(')===false) {throw new RuntimeException('Use Default label missing effective value: '.count($options).' '.($options[0]??''));}
    $checks++;
    $record=\Joomla\CMS\Plugin\PluginHelper::getPlugin('system','tagrouting'); $old=$record->params;
    foreach([0,1] as $value) {
        $settings=new \Joomla\Registry\Registry($old); $settings->set('facet_include_descendants',$value); $record->params=$settings->toString();
        if(\SuperSoft\Plugin\System\TagRouting\Configuration\RoutingOptions::descendants('')!==(bool)$value
            || \SuperSoft\Plugin\System\TagRouting\Configuration\RoutingOptions::descendants('0')!==false
            || \SuperSoft\Plugin\System\TagRouting\Configuration\RoutingOptions::descendants('1')!==true) {throw new RuntimeException('Default/Yes/No inheritance failed.');}
        $plugin->onContentPrepareForm(new \Joomla\Event\Event('onContentPrepareForm',['form'=>$menu,'data'=>new stdClass()]));
        $label=$menu->getXml()->xpath('//field[@name="tag_facet_descendants"]/option[@value=""]')[0];
        if(!str_contains((string)$label,\Joomla\CMS\Language\Text::_($value?'JYES':'JNO'))) {throw new RuntimeException('Default label value incorrect.');}
        $checks++;
    }
    $record->params=$old;
    $app->getInput()->set('component','com_content');
    $other=new \Joomla\CMS\Form\Form('com_config.component'); $other->load('<form><field name="native" type="text" /></form>');
    $plugin->onContentPrepareForm(new \Joomla\Event\Event('onContentPrepareForm',['form'=>$other]));
    if($other->getField('smarttag_enable_cleanup')) {throw new RuntimeException('Settings injected into unrelated component.');}
    $app->getInput()->set('component','com_tags'); $app->loadIdentity(new \Joomla\CMS\User\User());
    $restricted=new \Joomla\CMS\Form\Form('com_config.component'); $restricted->load('<form><field name="native" type="text" /></form>');
    $plugin->onContentPrepareForm(new \Joomla\Event\Event('onContentPrepareForm',['form'=>$restricted]));
    if($restricted->getField('smarttag_enable_cleanup')) {throw new RuntimeException('Unauthorised settings exposed.');}
    $app->loadIdentity(new \Joomla\CMS\User\User($userId)); $checks+=2;
    $pluginData=(object)['element'=>'tagrouting','folder'=>'system','params'=>['enable_custom_marker'=>1,'custom_marker'=>'!legacy','accepted_markers'=>['!tag']]];
    $plugin->onContentPrepareData(new \Joomla\Event\Event('onContentPrepareData',['context'=>'com_plugins.plugin','data'=>$pluginData]));
    if(!in_array('__custom__',$pluginData->params['accepted_markers'],true)||$pluginData->params['breadcrumb_marker']!=='#') {throw new RuntimeException('Original plugin form lost legacy custom/default values.');}
    $manifest=simplexml_load_string(base64_decode($formSources[2]));
    $originalForm=new \Joomla\CMS\Form\Form('com_plugins.plugin'); $originalForm->load('<form>'.$manifest->config->fields->asXML().'</form>');
    $originalForm->bind(['params'=>$pluginData->params]);
    $guide=$originalForm->getField('routing_help','params')->renderField();
    if(str_contains($guide,'hide-aware-inline-help')||!str_contains($guide,'/articles/!/blue,red+travel')
        ||!$originalForm->getXml()->xpath('//fieldset[@name="howto"]/field[@name="routing_help"]')
        ||$originalForm->getXml()->xpath('//fieldset[@name="basic"]/field[@name="routing_help"]')) {throw new RuntimeException('Help guide is not a separate always-visible plugin tab.');}
    ++$checks;
    if(!in_array('__custom__',$originalForm->getValue('accepted_markers','params'),true)) {throw new RuntimeException('Native plugin form binding lost custom selection.');}
    if(!str_contains($form->getField('smarttag_custom_marker')->input,'placeholder="!example"')
        ||!str_contains($originalForm->getField('custom_marker','params')->input,'placeholder="!example"')) {throw new RuntimeException('Custom marker hint missing in Tags Options or plugin.');}
    ++$checks;
    $ownFiltered=$originalForm->filter(['params'=>['enable_hierarchical'=>1,'show_breadcrumb_marker'=>1,'accepted_markers'=>['!tag'],'enable_custom_marker'=>1,'breadcrumb_marker'=>'']]);
    $ownSubject=(object)['type'=>'plugin','element'=>'tagrouting','folder'=>'system','params'=>(new \Joomla\Registry\Registry($ownFiltered['params']))->toString()];
    $plugin->onExtensionBeforeSave(new \Joomla\Event\Event('onExtensionBeforeSave',['context'=>'com_plugins.plugin','subject'=>$ownSubject]));
    $ownParams=new \Joomla\Registry\Registry($ownSubject->params);
    if($ownParams->get('enable_custom_marker')!==0||($ownParams->toArray()['breadcrumb_marker']??null)!=='') {throw new RuntimeException('Original plugin Save retained custom toggle or replaced empty marker: ' . json_encode($ownFiltered) . '');}
    $checks+=3;
} finally {unlink($temp);unlink($langTemp);}

// Shadow only this connection's extensions table. No installed configuration writes.
$rows=$db->setQuery("SELECT * FROM #__extensions WHERE (type='plugin' AND folder='system' AND element='tagrouting') OR (type='component' AND element='com_tags')")->loadObjectList();
$schema=$db->setQuery('SHOW CREATE TABLE #__extensions')->loadRow();
$db->setQuery(preg_replace('/^CREATE TABLE /','CREATE TEMPORARY TABLE ',$schema[1]))->execute();
foreach($rows as $row) {$db->insertObject('#__extensions',$row);}
$tags=new \Joomla\CMS\Table\Extension($db); $tags->load(['element'=>'com_tags','type'=>'component']);
$readPlugin=static function() use($db) { return (string)$db->setQuery("SELECT params FROM #__extensions WHERE element='tagrouting' AND folder='system' AND type='plugin'")->loadResult(); };
$oldPlugin=$readPlugin(); $oldTags=$tags->params;
$setInput=static function() use($tags) { $params=new \Joomla\Registry\Registry($tags->params); $params->set('smarttag_facet_include_descendants',1); $params->set('smarttag_accepted_markers',['short'=>'1','tag'=>'0','filter'=>'0','custom_enabled'=>'1']); $params->set('smarttag_custom_marker','!topic'); $tags->params=$params->toString(); };
$setInput();
$before=new \Joomla\Event\Event('onExtensionBeforeSave',['context'=>'com_config.component','subject'=>$tags]);
$after=new \Joomla\Event\Event('onExtensionAfterSave',['context'=>'com_config.component','subject'=>$tags]);
$plugin->onExtensionBeforeSave($before);
if(!$tags->store()) {throw new RuntimeException('Native table save failed.');}
$plugin->onExtensionAfterSave($after);
$saved=new \Joomla\Registry\Registry($readPlugin());
if($saved->get('facet_include_descendants')!==1||$saved->get('enable_custom_marker')!==1||$saved->get('custom_marker')!=='!topic') {throw new RuntimeException('Plugin settings were not saved.');}
$tags->load($tags->extension_id);
if($tags->params!==$oldTags) {throw new RuntimeException('SmartTag fields were persisted into com_tags.');}
$checks+=2;
$hidden=new \Joomla\Registry\Registry($tags->params); $hidden->set('smarttag_accepted_markers',['short'=>'1','tag'=>'0','filter'=>'0','custom_enabled'=>'0']); $tags->params=$hidden->toString();
$plugin->onExtensionBeforeSave($before); $tags->store(); $plugin->onExtensionAfterSave($after);
$preserved=new \Joomla\Registry\Registry($readPlugin());
if($preserved->get('custom_marker')!=='!topic'||$preserved->get('enable_custom_marker')!==0) {throw new RuntimeException('Hiding Custom lost its saved value.');}
$tags->load($tags->extension_id); ++$checks;
$coordinator=new \SuperSoft\Plugin\System\TagRouting\Configuration\RoutingOptionsSave($db);
$baseline=$readPlugin(); $setInput(); $params=new \Joomla\Registry\Registry($tags->params); $params->set('smarttag_accepted_markers',['short'=>'1','tag'=>'0','filter'=>'0','custom'=>'!abort']); $tags->params=$params->toString();
$coordinator->before('com_config.component',$tags); $tags->store(); $coordinator->abort();
if($readPlugin()!==$baseline) {throw new RuntimeException('Failed save did not roll back plugin values.');}
$tags->load($tags->extension_id); if($tags->params!==$oldTags) {throw new RuntimeException('Failed save did not roll back Tags values.');}
$checks+=2;
$flags=\SuperSoft\Plugin\System\TagRouting\Configuration\RoutingOptions::merge(['enable_custom_marker'=>1,'custom_marker'=>'!old','accepted_markers'=>['!tag']]);
if(!in_array('__custom__',$flags['accepted_markers'],true)) {throw new RuntimeException('Legacy custom marker selection lost.');}
$checks++;
$clear=\SuperSoft\Plugin\System\TagRouting\Configuration\RoutingOptions::normalize([
    'accepted_markers'=>['short'=>'0','tag'=>'1','filter'=>'0','custom'=>''], 'custom_marker'=>'!old-hidden', 'enable_custom_marker'=>1]);
if($clear['accepted_markers']!==['!tag']||$clear['custom_marker']!==''||$clear['enable_custom_marker']!==0) {throw new RuntimeException('Clearing the custom textbox did not disable custom routing.');}
$separate=\SuperSoft\Plugin\System\TagRouting\Configuration\RoutingOptions::normalize([
    'accepted_markers'=>['short'=>'1','tag'=>'0','filter'=>'0','custom_enabled'=>'1'], 'custom_marker'=>'!separate']);
if($separate['enable_custom_marker']!==1||$separate['custom_marker']!=='!separate') {throw new RuntimeException('Independent Custom field was not persisted.');}
++$checks;
$off=\SuperSoft\Plugin\System\TagRouting\Configuration\RoutingOptions::normalize([
    'accepted_markers'=>['short'=>'1','tag'=>'0','filter'=>'0','custom_enabled'=>'0','custom'=>'!remember']]);
if($off['enable_custom_marker']!==0||$off['custom_marker']!=='!remember'||in_array('__custom__',$off['accepted_markers'],true)) {throw new RuntimeException('Custom switch did not disable routing while retaining text.');}
$checks+=2;
echo json_encode(['result'=>'PASS','checks'=>$checks,'coverage'=>'Native Joomla forms/validation, shared save, plugin-only storage and rollback on connection-local temporary tables; no installed configuration changes'],JSON_PRETTY_PRINT),"\n";
