<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Tags\Site\Controller;

/** Include native type filters and explicit presentation in Joomla view cache keys. */
final class DisplayController extends \Joomla\Component\Tags\Site\Controller\DisplayController
{
    public function display($cachable = false, $urlparams = false)
    {
        $registered = clone ($this->app->registeredurlparams ?? new \stdClass());
        $registered->types = 'ARRAY';
        $registered->{'cat-view'} = 'CMD';
        $this->app->registeredurlparams = $registered;

        return parent::display($cachable, $urlparams);
    }
}
