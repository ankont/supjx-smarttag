<?php

declare(strict_types=1);

namespace SuperSoft\Component\TagRouting\Administrator\Extension;

use Joomla\CMS\Component\Router\RouterServiceInterface;
use Joomla\CMS\Component\Router\RouterServiceTrait;
use Joomla\CMS\Extension\MVCComponent;

final class TagRoutingComponent extends MVCComponent implements RouterServiceInterface
{
    use RouterServiceTrait;
}
