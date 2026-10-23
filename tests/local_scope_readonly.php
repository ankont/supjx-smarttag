<?php
/** CLI-only real-database scope comparison; never writes content or config. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit(1); }
define('_JEXEC', 1);
define('JPATH_BASE', '/var/www/html');
require JPATH_BASE . '/includes/defines.php';
require JPATH_BASE . '/includes/framework.php';
require_once JPATH_BASE . '/plugins/system/tagrouting/src/Domain/TagNode.php';
require_once JPATH_BASE . '/plugins/system/tagrouting/src/Infrastructure/CategoryTaxonomyResolver.php';
$db = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
$app = new \stdClass();
$reports = [];
foreach ([[1], [1, 5]] as $levels) {
    foreach (['el-GR', 'en-GB'] as $language) {
        $baseline = (new \SuperSoft\Plugin\System\TagRouting\Infrastructure\CategoryTaxonomyResolver($db))->resolve(12, $levels, $language);
        $shared = \SuperSoft\Plugin\System\TagRouting\Infrastructure\CategoryTaxonomyResolver::forApplication($app, $db)->resolve(12, $levels, $language);
        if ($baseline != $shared) { throw new \RuntimeException('Real DB scope results changed.'); }
        $reports[] = ['levels' => $levels, 'language' => $language, 'nodes' => count($shared['nodes']), 'same' => true, 'digest' => hash('sha256', serialize($shared))];
    }
}
echo json_encode($reports, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
