<?php
/**
 * This file is part of {@see https://github.com/slavcodev/ Slavcodev Projects}.
 */

declare(strict_types=1);

namespace Slavcodev\Symfony\Routing\Loader;

use InvalidArgumentException;
use Symfony\Component\Routing\RouteCollection;

final class LocalizedRoutesFactory implements CollectionFactory
{
    private $routeFactory;

    public function __construct(RouteFactory $routeFactory)
    {
        $this->routeFactory = $routeFactory;
    }

    public function createRouteCollection($localizedUrlTemplates, array $commonConfig): RouteCollection
    {
        Assert::isArray($localizedUrlTemplates, 'localized paths');

        if (!isset($commonConfig['path'])) {
            throw new InvalidArgumentException('Missing canonical path for localized routes.');
        }

        $commonConfig['defaults']['_canonical_route'] = $commonConfig['path'];
        unset($commonConfig['path']);

        $collection = new RouteCollection();

        foreach ($localizedUrlTemplates as $locale => $urlTemplate) {
            Assert::isString($urlTemplate, 'localized path');

            $config = ['path' => $urlTemplate, 'defaults' => ['_locale' => $locale]];

            RouteFactory::mergeConfigs($config, $commonConfig);

            $route = $this->routeFactory->createRoute($config);
            $collection->add($route->getDefault('_route'), $route);
        }

        return $collection;
    }
}
