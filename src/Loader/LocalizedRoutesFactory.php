<?php
/**
 * This file is part of {@see https://github.com/slavcodev/ Slavcodev Projects}.
 */

declare(strict_types=1);

namespace Slavcodev\Symfony\Routing\Loader;

use InvalidArgumentException;
use Symfony\Component\Routing\RouteCollection;
use function is_array;
use function is_string;

final class LocalizedRoutesFactory implements CollectionFactory
{
    private $routeFactory;

    public function __construct(RouteFactory $routeFactory)
    {
        $this->routeFactory = $routeFactory;
    }

    public function createRouteCollection($localizedUrlTemplates, array $commonConfig): RouteCollection
    {
        if (!is_array($localizedUrlTemplates)) {
            throw new InvalidArgumentException('The definition of the "locales" must be a YAML array.');
        }

        if (!isset($commonConfig['path'])) {
            throw new InvalidArgumentException('Missing canonical path for localized routes.');
        }

        $commonConfig['defaults']['_canonical_route'] = $commonConfig['path'];
        unset($commonConfig['path']);

        $collection = new RouteCollection();

        foreach ($localizedUrlTemplates as $locale => $urlTemplate) {
            if (!is_string($urlTemplate)) {
                throw new InvalidArgumentException('The localized path must be a string.');
            }

            $config = ['path' => $urlTemplate, 'defaults' => ['_locale' => $locale]];

            RouteFactory::mergeConfigs($config, $commonConfig);

            $route = $this->routeFactory->createRoute($config);
            $collection->add($route->getDefault('_route'), $route);
        }

        return $collection;
    }
}
