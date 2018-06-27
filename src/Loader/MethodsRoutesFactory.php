<?php
/**
 * This file is part of {@see https://github.com/slavcodev/ Slavcodev Projects}.
 */

declare(strict_types=1);

namespace Slavcodev\Symfony\Routing\Loader;

use InvalidArgumentException;
use Symfony\Component\Routing\RouteCollection;
use function strtoupper;

final class MethodsRoutesFactory implements CollectionFactory
{
    private $routeFactory;

    public function __construct(RouteFactory $routeFactory)
    {
        $this->routeFactory = $routeFactory;
    }

    public function createRouteCollection($methods, array $commonConfig): RouteCollection
    {
        Assert::isArray($methods, 'methods routes');
        Assert::noAllowedMethods($commonConfig, 'methods group definition');
        Assert::containCanonicalPath($commonConfig, 'methods group definition');

        $commonConfig['defaults']['_canonical_route'] = $commonConfig['path'];

        $collection = new RouteCollection();

        foreach ($methods as $method => $config) {
            if ($config === null) {
                $config = [];
            }

            Assert::isArray($config, 'method definition');
            Assert::noPath($config, 'method definition');
            Assert::noAllowedMethods($config, 'method definition');

            $method = strtoupper($method);
            $config['defaults']['_method'] = $method;
            $config['defaults']['_allowed_methods'] = $method;

            RouteFactory::mergeConfigs($config, $commonConfig);

            $route = $this->routeFactory->createRoute($config);
            $collection->add($route->getDefault('_route'), $route);
        }

        return $collection;
    }
}
