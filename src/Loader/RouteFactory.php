<?php
/**
 * This file is part of {@see https://github.com/slavcodev/ Slavcodev Projects}.
 */

declare(strict_types=1);

namespace Slavcodev\Symfony\Routing\Loader;

use InvalidArgumentException;
use Symfony\Component\Routing\Route;
use function array_diff_key;
use function strtolower;
use function trim;

final class RouteFactory
{
    public function create(array $config): Route
    {
        $defaults = $config['defaults'] ?? [];

        $route = new Route(
            $config['path'] ?? '',
            $defaults,
            $config['requirements'] ?? [],
            $config['options'] ?? [],
            $config['host'] ?? null,
            $config['schemes'] ?? null,
            $defaults['_allowed_methods'] ?? null,
            $config['condition'] ?? null
        );

        $route->addDefaults(array_diff_key($config, YamlFileLoader::SUPPORTED_KEYS, $defaults));

        if ($route->getDefault('controller') && $route->getDefault('_controller')) {
            throw new InvalidArgumentException('The definition must not specify both the "controller" key and the defaults key "_controller".');
        }

        if ($method = $route->getDefault('_method')) {
            if ($method === 'GET') {
                $routeNameSuffix = '';
                $route->setMethods(['GET', 'HEAD']);
            } else {
                $routeNameSuffix = '/' . strtolower($method);
                $route->setMethods([$method]);
            }

            $routeName = trim($route->getPath(), '/') . $routeNameSuffix;
            $route->addDefaults(['_allowed_methods' => $route->getMethods()]);
        } elseif ($locale = $route->getDefault('_locale')) {
            $routeName = trim($route->getDefault('_canonical_route'), '/') . '.' . $locale;
        } else {
            $routeName = trim($route->getPath(), '/');
        }

        $route->addDefaults(['_route' => $routeName]);

        return $route;
    }
}
