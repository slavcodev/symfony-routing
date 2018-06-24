<?php
/**
 * This file is part of {@see https://github.com/slavcodev/ Slavcodev Projects}.
 */

declare(strict_types=1);

namespace Slavcodev\Symfony\Routing\Loader;

use InvalidArgumentException;
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

    protected function parseImport(RouteCollection $collection, array $config, $path, $file)
    {
        // Conventional we use path as route name, so prefix should be same as name prefix
        if (!isset($config['prefix']) && isset($config['name_prefix'])) {
            $config['prefix'] = $config['name_prefix'];
        }

        $config['name_prefix'] = $config['prefix'];

        parent::parseImport($collection, $config, $path, $file);
    }

    protected function parseRoute(RouteCollection $collection, $urlTemplate, array $config, $path)
    {
        // Conventional we use path as route name
        $config['path'] = $urlTemplate;

        if (empty($config['methods']) || array_keys($config['methods']) === range(0, count($config['methods']) - 1)) {
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
            if (!empty($methodConfig) && !is_array($methodConfig)) {
                throw new InvalidArgumentException(sprintf('Route "%s" has incorrect methods definition.', $urlTemplate));
            }

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
                $routeName = $urlTemplate. '::' . $method;
            }

            parent::validate($methodConfig, $routeName, $path);
            parent::parseRoute($collection, $routeName, $methodConfig, $path);
        }
    }
}
