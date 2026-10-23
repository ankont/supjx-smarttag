<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Routing;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Router\SiteRouter;
use Joomla\CMS\Uri\Uri;
use SuperSoft\Plugin\System\TagRouting\Domain\SerializedExpression;

/** Internal query variables may be built by PHP, never supplied by a client. */
final readonly class InternalQueryBoundary
{
    public function __construct(private SiteApplication $app) {}

    public function parse(SiteRouter &$router, Uri &$uri): void
    {
        $query = $uri->getQuery(true);
        $changed = false;
        foreach ([SerializedExpression::REQUEST_KEY, TagPageState::REQUEST_KEY, TagPageState::SCOPE_KEY] as $key) {
            if (array_key_exists($key, $query)) {
                unset($query[$key]);
                $changed = true;
            }
        }
        if ($changed) {
            $uri->setQuery($query);
            if (in_array($this->app->getInput()->getMethod(), ['GET', 'HEAD'], true)) {
                $router->setTainted();
            }
        }
    }

    public function build(SiteRouter &$router, Uri &$uri): void
    {
        // Runs AFTER category/standalone build rules consume their internal
        // metadata, so valid filtered pagination has already become a SEF path.
        $uri->delVar(SerializedExpression::REQUEST_KEY);
        $uri->delVar(TagPageState::REQUEST_KEY);
        $uri->delVar(TagPageState::SCOPE_KEY);
    }
}
