<?php
declare(strict_types=1);
namespace SuperSoft\Plugin\System\SmartTagEnhancements\Site\Controller;

use Joomla\CMS\Form\Form;
use SuperSoft\Plugin\System\SmartTagEnhancements\Configuration\EnhancementOptions;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Tags\Site\Helper\RouteHelper;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use SuperSoft\Plugin\System\SmartTagEnhancements\ReturnUrl;
use SuperSoft\Plugin\System\SmartTagEnhancements\EditorTabs;

final class TagController extends BaseController
{
    protected $option = 'com_tags';

    public function execute($task)
    {
        if (!in_array($task, ['edit', 'save', 'apply', 'cancel'], true)) {
            throw new \RuntimeException(Text::_('JERROR_PAGE_NOT_FOUND'), 404);
        }
        return parent::execute($task);
    }

    public function edit(): void
    {
        [$model, $item, $form] = $this->loadEditor();
        $this->checkout($model, (int) $item->id);
        $this->renderForm($form, $item);
    }

    public function apply(): void
    {
        $this->save();
    }

    public function save(): void
    {
        $this->checkPost();
        [$model, $item, $form] = $this->loadEditor();
        $data = $this->input->post->get('jform', [], 'array');
        if ((int) ($data['id'] ?? 0) !== (int) $item->id) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        $this->checkout($model, (int) $item->id);
        $allowed = ['id', 'title', 'alias', 'description', 'parent_id', 'note'];
        if (EnhancementOptions::customFields()) { $allowed[] = 'com_fields'; }
        if ($this->app->getIdentity()->authorise('core.edit.state', 'com_tags')) {
            $allowed = array_merge($allowed, ['published', 'access', 'language']);
        }
        $data = array_intersect_key($data, array_flip($allowed));
        $normalised = (object) $data;
        $this->app->getDispatcher()->dispatch('onContentNormaliseRequestData',
            new \Joomla\CMS\Event\Model\NormaliseRequestDataEvent('onContentNormaliseRequestData', [
                'context' => 'com_tags.tag', 'data' => $normalised, 'subject' => $form,
            ]));
        $data = (array) $normalised;
        $valid = $model->validate($form, $data);
        if ($valid === false) {
            foreach ($model->getErrors() as $error) {
                $this->app->enqueueMessage($error instanceof \Throwable ? $error->getMessage() : (string) $error, 'warning');
            }
            $form->bind($data);
            $this->renderForm($form, $item, $data);
            return;
        }
        // Preserve omitted native properties, and let native Fields enforce value permissions.
        $saved = array_replace((array) $item, array_intersect_key($valid, array_flip($allowed)));
        unset($saved['rules']);
        if (!$model->save($saved)) {
            $this->app->enqueueMessage((string) $model->getError(), 'error');
            $form->bind($data);
            $this->renderForm($form, $item, $data);
            return;
        }
        if ($model->checkin((int) $item->id) === false) {
            throw new \RuntimeException((string) $model->getError(), 409);
        }
        $return = $this->returnUrl((int) $item->id);
        $this->setRedirect($this->getTask() === 'apply' ? $this->editUrl((int) $item->id, $return) : $return,
            Text::_('PLG_SYSTEM_SMARTTAGENHANCEMENTS_SAVED'));
    }

    public function cancel(): void
    {
        $this->checkPost();
        [$model, $item] = $this->loadEditor();
        if ($model->checkin((int) $item->id) === false) {
            throw new \RuntimeException((string) $model->getError(), 409);
        }
        $this->setRedirect($this->returnUrl((int) $item->id));
    }

    private function loadEditor(): array
    {
        if (!EnhancementOptions::frontendEditing()) {
            throw new \RuntimeException(Text::_('JERROR_PAGE_NOT_FOUND'), 404);
        }
        $rawId = $this->input->get('id', null, 'raw');
        if (is_array($rawId)) {
            $rawId = count($rawId) === 1 ? reset($rawId) : null;
        }
        $rawId = is_scalar($rawId) ? explode(':', (string) $rawId, 2)[0] : '';
        $id = ctype_digit($rawId) ? (int) $rawId : 0;
        $user = $this->app->getIdentity();
        if ($this->input->getCmd('format', 'html') !== 'html') {
            throw new \RuntimeException(Text::_('JERROR_PAGE_NOT_FOUND'), 404);
        }
        if ($user->guest) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        if ($id <= 1) {
            throw new \RuntimeException(Text::_('JERROR_PAGE_NOT_FOUND'), 404);
        }
        $this->input->set('id', $id);
        $model = $this->factory->createModel('Tag', 'Administrator', ['ignore_request' => true]);
        $model->setCurrentUser($user);
        $model->setState('tag.id', $id);
        $item = $model->getItem($id);
        if (!$item || (int) $item->id !== $id) {
            throw new \RuntimeException(Text::_('JERROR_PAGE_NOT_FOUND'), 404);
        }
        $canEdit = $user->authorise('core.edit', 'com_tags')
            || ($user->authorise('core.edit.own', 'com_tags') && (int) $item->created_user_id === (int) $user->id);
        if (!$canEdit || !in_array((int) $item->access, $user->getAuthorisedViewLevels(), true)) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        $this->app->getLanguage()->load('com_tags', JPATH_ADMINISTRATOR);
        PluginHelper::importPlugin('content');
        Form::addFormPath(JPATH_ADMINISTRATOR . '/components/com_tags/forms');
        $form = $model->getForm((array) $item, false);
        if (!$form) {
            throw new \RuntimeException((string) $model->getError(), 500);
        }
        if (EnhancementOptions::customFields() && PluginHelper::isEnabled('system', 'fields')) {
            FieldsHelper::prepareForm('com_tags.tag', $form, $item);
        }
        $form->removeField('rules');
        if ($user->authorise('core.edit.state', 'com_tags')) {
            $form->setFieldAttribute('published', 'disabled', 'false');
            $form->setFieldAttribute('published', 'filter', 'integer');
        } else {
            foreach (['published', 'access', 'language'] as $field) {
                $form->removeField($field);
            }
        }
        // Allow native content plugins to prepare the remaining item data.
        $data = (array) $item;
        $event = new \Joomla\CMS\Event\Model\PrepareDataEvent('onContentPrepareData', [
            'context' => 'com_tags.tag', 'data' => &$data,
        ]);
        $this->app->getDispatcher()->dispatch('onContentPrepareData', $event);
        $form->bind($event->getData());
        return [$model, $item, $form];
    }

    private function checkout($model, int $id): void
    {
        if (!$model->checkout($id)) {
            throw new \RuntimeException((string) $model->getError(), 409);
        }
    }

    private function checkPost(): void
    {
        if ($this->input->getMethod() !== 'POST'
            || $this->input->post->get(Session::getFormToken(), '', 'alnum') !== '1') {
            throw new \RuntimeException(Text::_('JINVALID_TOKEN'), 403);
        }
    }

    private function returnUrl(int $id): string
    {
        $fallback = Route::_(RouteHelper::getComponentTagRoute((string) $id), false);
        return ReturnUrl::decode($this->input->getString('return', ''), $fallback);
    }

    private function editUrl(int $id, string $return): string
    {
        return Uri::root() . 'index.php?option=com_tags&task=tag.edit&id=' . $id
            . '&return=' . rawurlencode(base64_encode($return));
    }

    private function renderForm(Form $form, object $item, ?array $invalidData = null): void
    {
        HTMLHelper::_('behavior.formvalidator');
        $presentation = EditorTabs::prepare($form);
        $invalid = $invalidData === null ? null : EditorTabs::firstInvalid($form, $presentation['tabs'], $invalidData);
        $this->app->getDocument()->getWebAssetManager()->registerAndUseScript(
            'plg_system_smarttagenhancements.editor', 'plg_system_smarttagenhancements/editor.js',
            ['version' => 'auto'], ['defer' => true], ['core', 'form.validate', 'webcomponent.joomla-tab']
        );
        $this->app->setHeader('Cache-Control', 'private, no-store', true);
        $this->app->getDocument()->setTitle(Text::_('PLG_SYSTEM_SMARTTAGENHANCEMENTS_EDIT'));
        $action = Uri::root() . 'index.php?option=com_tags&id=' . (int) $item->id;
        echo (new FileLayout('edit', dirname(__DIR__, 3) . '/layouts', ['component' => 'plg_system_smarttagenhancements', 'client' => 0]))->render([
            'form' => $form, 'item' => $item, 'action' => $action,
            'tabs' => $presentation['tabs'], 'hiddenFields' => $presentation['hidden'],
            'activeTab' => $invalid['tab'] ?? array_key_first($presentation['tabs']),
            'invalidField' => $invalid['field'] ?? '', 'validationFailed' => $invalidData !== null,
            'return' => base64_encode($this->returnUrl((int) $item->id)),
        ]);
    }
}
