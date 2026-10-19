<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\MVC;

use Psr\Log\LoggerInterface;

final class FacetedMVCFactory extends \Joomla\CMS\MVC\Factory\MVCFactory
{
    public function __construct(private readonly string $overlayNamespace, ?LoggerInterface $logger = null, private readonly string $coreNamespace = 'Joomla\\Component\\Content')
    {
        parent::__construct($overlayNamespace, $logger);
    }

    protected function getClassName(string $suffix, string $prefix)
    {
        $overlay = trim($this->overlayNamespace, '\\') . '\\' . ucfirst($prefix) . '\\' . $suffix;

        if (class_exists($overlay)) {
            return $overlay;
        }

        $core = trim($this->coreNamespace, '\\') . '\\' . ucfirst($prefix) . '\\' . $suffix;

        return class_exists($core) ? $core : null;
    }
}
