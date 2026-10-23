<?php
declare(strict_types=1);
namespace Joomla\Component\Tags\Site\Controller {
    class DisplayController {
        public function __construct(protected object $app) {}
        public function display($cachable = false, $urlparams = false) {
            $this->app->registeredurlparams->id = 'ARRAY';
            $this->app->registeredurlparams->Itemid = 'INT';
            return $this;
        }
    }
}
namespace {
    require dirname(__DIR__).'/package/plugins/system/tagrouting/src/Tags/Site/Controller/DisplayController.php';
    $keys=[];
    foreach ([[],[1],[5],[1,5]] as $types) {
        $app=(object)['registeredurlparams'=>(object)['custom'=>'STRING']];
        $controller=new \SuperSoft\Plugin\System\TagRouting\Tags\Site\Controller\DisplayController($app);
        if ($controller->display()!==$controller || $app->registeredurlparams->types!=='ARRAY' || $app->registeredurlparams->custom!=='STRING' || $app->registeredurlparams->{'cat-view'}!=='CMD') {throw new \RuntimeException('Cache parameters/chaining lost.');}
        $keys[]=json_encode(array_intersect_key(['id'=>[7],'Itemid'=>141,'types'=>$types],(array)$app->registeredurlparams));
    }
    if (count(array_unique($keys))!==4) {throw new \RuntimeException('Native type filter output cache collision.');}
    echo "OK (tag view type-filter cache contract)\n";
}
