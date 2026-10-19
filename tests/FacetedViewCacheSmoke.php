<?php

declare(strict_types=1);

// Dependency-free controller contract test. The stub models Joomla's core
// replacement of $urlparams and subsequent merge into registeredurlparams.
namespace Joomla\Component\Content\Site\Controller {
    class DisplayController
    {
        public function __construct(protected object $app)
        {
        }

        public function display($cachable = false, $urlparams = false)
        {
            $safe = ['id' => 'INT', 'Itemid' => 'INT'];
            foreach ($safe as $key => $type) {
                $this->app->registeredurlparams->$key = $type;
            }

            return $this;
        }
    }
}

namespace {
    require dirname(__DIR__) . '/package/plugins/system/tagrouting/src/Domain/SerializedExpression.php';
    require dirname(__DIR__) . '/package/plugins/system/tagrouting/src/Site/Controller/DisplayController.php';

    $keys = [];
    foreach (['', 'first-expression', 'second-expression'] as $expression) {
        $app = (object) ['registeredurlparams' => (object) ['custom' => 'STRING']];
        $controller = new \SuperSoft\Plugin\System\TagRouting\Site\Controller\DisplayController($app);
        if ($controller->display() !== $controller) {
            throw new \RuntimeException('Controller chaining was lost.');
        }
        if (($app->registeredurlparams->__tagrouting ?? null) !== 'STRING'
            || ($app->registeredurlparams->custom ?? null) !== 'STRING'
            || ($app->registeredurlparams->Itemid ?? null) !== 'INT') {
            throw new \RuntimeException('Facet or existing cache parameters were lost.');
        }
        $input = ['id' => 9, 'Itemid' => 106, '__tagrouting' => $expression];
        $keys[] = json_encode(array_intersect_key($input, (array) $app->registeredurlparams));
    }
    if (count(array_unique($keys)) !== 3) {
        throw new \RuntimeException('Unfiltered and faceted cache inputs collided.');
    }
    $app = new \stdClass();
    (new \SuperSoft\Plugin\System\TagRouting\Site\Controller\DisplayController($app))->display();
    if (($app->registeredurlparams->__tagrouting ?? null) !== 'STRING') {
        throw new \RuntimeException('Missing initial registry was not handled.');
    }
    echo "OK (faceted view cache controller contract)\n";
}
