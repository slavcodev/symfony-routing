<?php
/**
 * This file is part of {@see https://github.com/slavcodev/ Slavcodev Projects}.
 */

declare(strict_types=1);

namespace Slavcodev\Symfony\Routing\Loader;

use Symfony\Component\Routing\RouteCollection;

interface CollectionFactory
{
    public function createRouteCollection($config, array $defaultConfig): RouteCollection;
}
