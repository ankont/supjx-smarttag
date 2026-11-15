<?php
declare(strict_types=1);
namespace SuperSoft\Plugin\System\SmartTagEnhancements\Extension;

use Joomla\CMS\Component\Router\RouterFactoryInterface;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use SuperSoft\Plugin\System\SmartTagEnhancements\Configuration\EnhancementOptions;
use SuperSoft\Plugin\System\SmartTagEnhancements\Configuration\OptionsForm;
use SuperSoft\Plugin\System\SmartTagEnhancements\Configuration\OptionsSave;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Menu\AdministratorMenuItem;
use Joomla\CMS\Uri\Uri;
use Joomla\DI\Container;
use Joomla\Event\Event;
use Joomla\Event\Priority;
use Joomla\Event\SubscriberInterface;
use SuperSoft\Plugin\System\SmartTagEnhancements\Component\TagsComponent;
use SuperSoft\Plugin\System\SmartTagEnhancements\Service\FactoryProvider;
use SuperSoft\Plugin\System\SmartTagEnhancements\TagFields;
use SuperSoft\Plugin\System\SmartTagEnhancements\MVC\EditorFactory;

final class SmartTagEnhancements extends CMSPlugin implements SubscriberInterface
{
    private ?OptionsSave $optionsSave = null;

    public static function getSubscribedEvents(): array
    {
        return ['onAfterExtensionBoot'=>['onAfterExtensionBoot', Priority::LOW], 'onContentPrepareForm'=>['onContentPrepareForm', Priority::LOW],
            'onAfterInitialise'=>'onAfterInitialise', 'onPreprocessMenuItems'=>['onPreprocessMenuItems', Priority::HIGH],
            'onContentPrepare'=>'onContentPrepare',
            'onExtensionBeforeSave'=>'onExtensionBeforeSave', 'onExtensionAfterSave'=>'onExtensionAfterSave'];
    }

    public function onAfterExtensionBoot(Event $event): void
    {
        if ($event->getArgument('type') === ComponentInterface::class
            && $event->getArgument('extensionName') === 'fields'
            && in_array($this->getApplication()->getName(), ['administrator', 'site'], true)) {
            $event->getArgument('container')->registerServiceProvider(new FactoryProvider('fields'));
            return;
        }
        if ($event->getArgument('type') !== ComponentInterface::class || $event->getArgument('extensionName') !== 'tags') { return; }
        $this->loadLanguage();
        $container = $event->getArgument('container');
        // Only the administrator needs an overlay model for native copy operations.
        // Frontend models and routing remain delegated to the existing factory.
        if ($this->getApplication()->isClient('administrator') && EnhancementOptions::customFields()) {
            $container->registerServiceProvider(new FactoryProvider());
        }
        if ($this->getApplication()->isClient('site') && EnhancementOptions::frontendEditing()) {
            $container->extend(MVCFactoryInterface::class, static function (MVCFactoryInterface $factory): MVCFactoryInterface {
                return $factory instanceof EditorFactory ? $factory : new EditorFactory($factory);
            });
        }
        if (!EnhancementOptions::customFields()) { return; }
        $container->set(ComponentInterface::class, static function (Container $container): ComponentInterface {
            $component = new TagsComponent($container->get(ComponentDispatcherFactoryInterface::class));
            $component->setMVCFactory($container->get(MVCFactoryInterface::class));
            $component->setRouterFactory($container->get(RouterFactoryInterface::class));
            return $component;
        });
    }

    public function onContentPrepareForm(Event $event): void
    {
        $form = $event->getArgument('form');
        if (!$form instanceof Form) { return; }
        $name = $form->getName();
        if ($name === TagFields::CONTEXT && !EnhancementOptions::customFields()) {
            $form->removeGroup('com_fields');
            return;
        }
        if (!$this->getApplication()->isClient('administrator')) { return; }
        if ($name === 'com_config.component'
            && $this->getApplication()->getInput()->getCmd('component') === 'com_tags'
            && $this->getApplication()->getIdentity()->authorise('core.edit', 'com_plugins')) {
            $this->loadLanguage();
            OptionsForm::add($form);
        }
        $data = $event->getArgument('data');
        $context = is_object($data) ? ($data->context ?? '') : ($data['context'] ?? '');
        if ($context === '') { $context = $this->getApplication()->getInput()->getCmd('context'); }
        $section = null;
        if (str_starts_with($name, 'com_fields.field') && $context === TagFields::CONTEXT) { $section = 'field'; }
        if (str_starts_with($name, 'com_fields.group') && $context === TagFields::CONTEXT) { $section = 'fieldgroup'; }
        if ($name === 'com_config.component' && $this->getApplication()->getInput()->getCmd('component') === 'com_tags') { $section = 'component'; }
        if ($section === null) { return; }
        // RulesField and RulesRule both support inline actions. Keep the com_tags
        // asset hierarchy; never borrow another component's permissions.
        foreach ($form->getXml()->xpath('//field[@name="rules"]') ?: [] as $rules) {
            $actions = $section === 'component' ? ['core.edit.value'] : ['core.delete','core.edit','core.edit.state','core.edit.value'];
            if ($section === 'fieldgroup') { $actions = ['core.create','core.delete','core.edit','core.edit.state','core.edit.own','core.edit.value']; }
            $titles = ['core.create'=>'JACTION_CREATE','core.delete'=>'JACTION_DELETE','core.edit'=>'JACTION_EDIT',
                'core.edit.state'=>'JACTION_EDITSTATE','core.edit.own'=>'JACTION_EDITOWN','core.edit.value'=>'JACTION_EDITVALUE'];
            foreach ($actions as $action) {
                if ($rules->xpath('action[@name="' . $action . '"]')) { continue; }
                $node = $rules->addChild('action'); $node->addAttribute('name', $action); $node->addAttribute('title', $titles[$action]);
            }
        }
    }

    public function onExtensionBeforeSave(Event $event): void
    {
        if (!$this->getApplication()->isClient('administrator')) { return; }
        $subject = $event->getArgument('subject');
        if (!is_object($subject) || $event->getArgument('context') !== 'com_config.component'
            || ($subject->element ?? '') !== 'com_tags') { return; }
        $params = new \Joomla\Registry\Registry($subject->params ?? '');
        $submitted = array_intersect_key($params->toArray(), [
            'enhancements_enable_frontend_editing'=>true, 'enhancements_enable_custom_fields'=>true,
        ]);
        if ($submitted === []) { return; }
        if (!$this->getApplication()->getIdentity()->authorise('core.edit', 'com_plugins')) {
            throw new \RuntimeException('Not authorised to edit SmartTag Enhancements settings.', 403);
        }
        $this->optionsSave ??= new OptionsSave(Factory::getContainer()->get(DatabaseInterface::class));
        $this->optionsSave->before('com_config.component', $subject);
    }

    public function onExtensionAfterSave(Event $event): void
    {
        $subject = $event->getArgument('subject');
        if ($this->optionsSave !== null && is_object($subject)) {
            $this->optionsSave->after((string) $event->getArgument('context'), $subject);
        }
    }

    public function onAfterInitialise(Event $event): void
    {
        $app = $this->getApplication();
        if (!EnhancementOptions::customFields() || !$app->isClient('administrator') || PluginHelper::isEnabled('system', 'fields')) { return; }
        $input = $app->getInput();
        if ($input->getCmd('option') === 'com_tags'
            || ($input->getCmd('option') === 'com_fields' && $input->getCmd('context') === TagFields::CONTEXT)) {
            $this->loadLanguage();
            $app->enqueueMessage(Text::_('PLG_SYSTEM_SMARTTAGENHANCEMENTS_REQUIRE_FIELDS'), 'warning');
        }
    }

    public function onPreprocessMenuItems(Event $event): void
    {
        $app = $this->getApplication();
        if (!EnhancementOptions::customFields() || !$app->isClient('administrator')
            || $event->getArgument('context') !== 'com_menus.administrator.module'
            || !$app->getIdentity()->authorise('core.manage', 'com_tags')) {
            return;
        }

        // Group native nodes before other integrations rewrite manager URLs.
        $this->loadLanguage();
        $items = $event->getArgument('subject', []);
        foreach ($items as $index => $item) {
            $uri = new Uri(html_entity_decode((string) $item->link, ENT_QUOTES, 'UTF-8'));
            $groupId = 'smarttagenhancements-menu-' . $item->id;
            if ($uri->getVar('option') !== 'com_tags' || $uri->getVar('task')
                || !in_array($uri->getVar('view', 'tags'), ['', 'tags'], true)
                || $item->getParent()?->id === $groupId) {
                continue;
            }

            $parent = $item->getParent();
            $group = new AdministratorMenuItem([
                'id' => $groupId,
                'title' => 'SupJx SmartTags',
                'type' => 'heading',
                'link' => '#',
                'class' => $item->class,
                'icon' => 'class:icon-tags',
                'params' => clone $item->getParams(),
            ]);
            if ($parent !== null) {
                $parent->addChild($group);
            }
            $group->addChild($item);
            foreach (['fields' => 'FIELDS', 'groups' => 'GROUPS'] as $view => $label) {
                $group->addChild(new AdministratorMenuItem([
                    'id' => $groupId . '-' . $view,
                    'title' => Text::_('PLG_SYSTEM_SMARTTAGENHANCEMENTS_' . $label),
                    'type' => 'component',
                    'link' => 'index.php?option=com_fields&view=' . $view . '&context=' . TagFields::CONTEXT,
                    'icon' => $view === 'fields' ? 'class:icon-puzzle-piece' : 'class:icon-folder',
                ]));
            }
            $items[$index] = $group;
        }
        $event->updateItems($items);
    }

    public function onContentPrepare(Event $event): void
    {
        if (!EnhancementOptions::customFields() || $event->getArgument('context') !== TagFields::CONTEXT) { return; }
        $item = $event->getArgument('subject');
        // In native tag views this same event also describes tagged articles.
        if (!is_object($item) || !empty($item->type_alias) || isset($item->jcfields)) { return; }
        TagFields::attach($item);
    }
}
