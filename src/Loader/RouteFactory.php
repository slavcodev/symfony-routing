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
    private const SUPPORTED_KEYS = [
        'path' => true,
        'host' => true,
        'schemes' => true,
        'defaults' => true,
        'requirements' => true,
        'options' => true,
        'condition' => true,
    ];

    public static function mergeConfigs(array &$config, array $defaultConfig)
    {
        if (!empty($defaultConfig)) {
            if (isset($defaultConfig['path'], $config['path'])) {
                $config['path'] = trim($defaultConfig['path'], '/') . '/' . ltrim($config['path'], '/');
            }

            $config = array_merge($defaultConfig, $config);

            // Recursively merge iterable keys.
            foreach (['defaults', 'requirements', 'options'] as $iterableKey) {
                if (isset($defaultConfig[$iterableKey], $config[$iterableKey])) {
                    $config[$iterableKey] = array_merge($defaultConfig[$iterableKey], $config[$iterableKey]);
                }
            }
        }
    }

    public function createRoute(array $config): Route
    {
        $path = $config['path'] ?? '';

        if (!is_string($path)) {
            throw new InvalidArgumentException('The path must be a string.');
        }

        $route = new Route(
            $path,
            $config['defaults'] ?? [],
            $config['requirements'] ?? [],
            $config['options'] ?? [],
            $config['host'] ?? null,
            $config['schemes'] ?? null,
            null,
            $config['condition'] ?? null
        );

        $route->addDefaults(array_diff_key($config, self::SUPPORTED_KEYS, $route->getDefaults()));
        $route->setMethods($route->getDefault('_allowed_methods'));
        $route->addDefaults(['_route' => $this->pickRouteName($route)]);

        return $route;
    }

    private function pickRouteName(Route $route): string
    {
        if ($method = $route->getDefault('_method')) {
            return $method === 'GET'
                ? trim($route->getDefault('_canonical_route'), '/')
                : trim($route->getDefault('_canonical_route'), '/') . '/' . strtolower($method);
        }

        if ($locale = $route->getDefault('_locale')) {
            return trim($route->getDefault('_canonical_route'), '/') . '.' . $locale;
        }

        return trim($route->getPath(), '/');
    }
}
