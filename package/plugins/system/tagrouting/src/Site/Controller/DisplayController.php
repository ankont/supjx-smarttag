<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Site\Controller;

use SuperSoft\Plugin\System\TagRouting\Domain\SerializedExpression;

/** Keeps category output caches distinct for every faceted request. */
final class DisplayController extends \Joomla\Component\Content\Site\Controller\DisplayController
{
    public function display($cachable = false, $urlparams = false)
    {
        // The core content controller replaces its $urlparams argument. Register
        // our parameter on the application instead; BaseController merges it
        // with the core safe parameters before computing the view cache key.
        // Register even for an unfiltered request, so it cannot reuse old keys.
        $registered = clone ($this->app->registeredurlparams ?? new \stdClass());
        $registered->{SerializedExpression::REQUEST_KEY} = 'STRING';
        $registered->{'cat-view'} = 'CMD';
        $this->app->registeredurlparams = $registered;

        return parent::display($cachable, $urlparams);
    }
}
