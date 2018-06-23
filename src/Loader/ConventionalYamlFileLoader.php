<?php
/**
 * This file is part of {@see https://github.com/slavcodev/ Slavcodev Projects}.
 */

declare(strict_types=1);

namespace Slavcodev\Symfony\Routing\Loader;

use Symfony\Component\Routing\Loader\YamlFileLoader;
use Symfony\Component\Routing\RouteCollection;

final class ConventionalYamlFileLoader extends YamlFileLoader
{
    protected function validate($config, $urlTemplate, $path)
    {
        // Conventional we use path as route name,
        // so need to set path key to avoid validation errors.
        if (!isset($config['resource'])) {
            $config['path'] = $urlTemplate;
        }

        parent::validate($config, $urlTemplate, $path);
    }

    protected function parseRoute(RouteCollection $collection, $urlTemplate, array $config, $path)
    {
        // Conventional we use path as route name
        if (!isset($config['resource'])) {
            $config['path'] = $urlTemplate;
        }

        if (empty($config['methods']) || is_integer(key($config['methods']))) {
            parent::parseRoute($collection, $urlTemplate, $config, $path);
        } else {
            $this->parseMethodRoutes($collection, $config['methods'], $urlTemplate, $config, $path);
        }
    }

    protected function parseMethodRoutes(RouteCollection $collection, array $methods, $urlTemplate, array $config, $path)
    {
        $sharedController = $config['controller'] ?? $config['defaults']['_controller'] ?? null;
        unset($config['controller'], $config['defaults']['_controller']);

        foreach ($methods as $method => $methodConfig) {
            $methodConfig = array_merge($config, (array) $methodConfig);
            $method = strtolower($method);

            $controller = $methodConfig['controller'] ?? $methodConfig['defaults']['_controller'] ?? $sharedController;
            unset($methodConfig['controller']);

            if (is_string($controller) && strpos($controller, '::') === false) {
                $controller .= '::' . $method;
            }

            $methodConfig['defaults']['_controller'] = $controller;

            if ($method === 'get') {
                $methodConfig['methods'] = [$method, 'head'];
                $routeName = $urlTemplate;
            } else {
                $methodConfig['methods'] = [$method];
                $routeName = $method . '::' . $urlTemplate;
            }

            parent::validate($methodConfig, $routeName, $path);
            parent::parseRoute($collection, $routeName, $methodConfig, $path);
        }
    }
}
