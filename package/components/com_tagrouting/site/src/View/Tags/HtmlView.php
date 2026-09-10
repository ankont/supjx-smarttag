<?php

declare(strict_types=1);

namespace SuperSoft\Component\TagRouting\Site\View\Tags;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;
use SuperSoft\Component\TagRouting\Site\Model\VirtualTagModel;

final class HtmlView extends BaseHtmlView
{
    public Registry $params;

    public string $landingTitle = '';

    public string $landingIcon = '';

    public function display($tpl = null): void
    {
        $app = Factory::getApplication();
        $this->params = $app->getParams();

        if ((string) $this->params->get('landing_page', 'virtual_root') === 'virtual_root') {
            $this->displayVirtualRoot($tpl);

            return;
        }

        parent::display($tpl);
    }

    private function displayVirtualRoot($tpl): void
    {
        $app = Factory::getApplication();
        $params = clone ComponentHelper::getParams('com_tags');
        $params->merge($this->params);
        $this->landingTitle = trim((string) $params->get('landing_title', 'Φίλτρο'));
        $this->landingIcon = $this->normaliseIconClass((string) $params->get('landing_icon', 'fas fa-filter'));

        if ($this->landingTitle === '') {
            $this->landingTitle = 'Φίλτρο';
        }

        $params->set('supjx_virtual_root', 1);
        $params->set('supjx_virtual_root_title', $this->landingTitle);
        $params->set('supjx_virtual_root_icon', $this->landingIcon);
        $params->set('show_tag_title', 0);
        $params->set('show_feed_link', 0);

        echo $this->loadTemplate('heading');

        $app->getLanguage()->load('com_tags', JPATH_SITE);
        $this->getDocument()->getWebAssetManager()->getRegistry()->addExtensionRegistryFile('com_tags');
        $component = $app->bootComponent('com_tags');
        $view = $component->getMVCFactory()->createView(
            'Tag',
            'Site',
            'Html',
            [
                'base_path' => JPATH_SITE . '/components/com_tags',
                'option' => 'com_tags',
            ],
        );
        $view->setDocument($this->getDocument());
        $view->setLanguage($app->getLanguage());
        $view->setCurrentUser($app->getIdentity());
        $view->setModel(
            new VirtualTagModel($params, Factory::getContainer()->get(DatabaseInterface::class)),
            true,
        );

        $active = $app->getMenu()->getActive();
        $originalQuery = $active?->query;

        if ($active !== null) {
            $active->query = ['option' => 'com_tags', 'view' => 'tag', 'id' => []];
        }

        try {
            $view->display($tpl);
        } finally {
            if ($active !== null) {
                $active->query = $originalQuery;
            }
        }
    }

    private function normaliseIconClass(string $icon): string
    {
        $classes = preg_split('/\s+/', trim($icon)) ?: [];
        $classes = array_values(array_filter(
            $classes,
            static fn (string $class): bool => $class !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $class) === 1,
        ));

        return $classes === [] ? 'fas fa-filter' : implode(' ', $classes);
    }
}
