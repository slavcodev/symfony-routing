<?php
/**
 * This file is part of {@see https://github.com/slavcodev/ Slavcodev Projects}.
 */

declare(strict_types=1);

namespace Slavcodev\Symfony\Routing\Loader;

use InvalidArgumentException;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\Config\Loader\FileLoader;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Yaml;
use function basename;
use function dirname;
use function in_array;
use function is_array;
use function is_string;
use function ltrim;
use function pathinfo;
use function sprintf;
use function strtolower;
use function strtoupper;
use function trim;

final class YamlFileLoader extends FileLoader
{
    public const SUPPORTED_KEYS = [
        // Keys which specify parsing behavior
        'resource' => true,
        'group' => true,
        'methods' => true,
        'locales' => true,
        // Route definition keys
        'path' => true,
        'host' => true,
        'schemes' => true,
        'defaults' => true,
        'requirements' => true,
        'options' => true,
        'condition' => true,
    ];

    public const SPECIAL_KEYS = [
        'resource' => true,
        'group' => true,
        'methods' => true,
        'locales' => true,
    ];

    public const SUPPORTED_METHODS = ['OPTIONS', 'HEAD', 'GET', 'POST', 'PUT', 'DELETE', 'PATCH'];

    private $yamlParser;

    public function __construct(FileLocatorInterface $locator)
    {
        parent::__construct($locator);
        $this->yamlParser = new Parser();
    }

    public function load($filename, $type = null): RouteCollection
    {
        $filepath = $this->locator->locate($filename);

        Assert::fileResource($filepath);

        $file = new FileResource($filepath);

        try {
            $parsedConfig = $this->yamlParser->parseFile($file->getResource(), Yaml::PARSE_CONSTANT);
        } catch (ParseException $e) {
            throw new InvalidArgumentException(sprintf('The file "%s" does not contain valid YAML.', $filepath), 0, $e);
        }

        if (!is_array($parsedConfig)) {
            throw new InvalidArgumentException(sprintf('The file "%s" must contain a YAML array.', $filepath));
        }

        $collection = new RouteCollection();
        $collection->addResource($file);

        foreach ($parsedConfig as $config) {
            $this->parseDefinition($collection, $file, $config, []);
        }

        return $collection;
    }

    public function supports($resource, $type = null): bool
    {
        return is_string($resource) && in_array(pathinfo($resource, PATHINFO_EXTENSION), ['yml', 'yaml'], true) && !$type;
    }

    private function parseDefinition(RouteCollection $collection, FileResource $file, $config, array $defaultConfig)
    {
        Assert::definition($config);

        if ($defaultConfig) {
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

        $cleanConfig = array_diff_key($config, self::SPECIAL_KEYS);

        if ($cleanConfig === $config) {
            $this->addRoute($collection, $config);

            return;
        }

        if (count($config) - count($cleanConfig) > 1) {
            throw new InvalidArgumentException('The definition must not specify more than one special "resource", "group", "methods" or "locale" keys.');
        }

        if (isset($config['resource'])) {
            $subCollection = $this->importRoutes($file, $config['resource'], $cleanConfig);
            $collection->addCollection($subCollection);
        } elseif (isset($config['group'])) {
            $subCollection = $this->createGroupRoutes($file, $config['group'], $cleanConfig);
            $collection->addCollection($subCollection);
        } elseif (isset($config['methods'])) {
            $subCollection = $this->createMethodsRoutes($file, $config['methods'], $cleanConfig);
            $collection->addCollection($subCollection);
        } elseif (isset($config['locales'])) {
            $subCollection = $this->createLocalizedRoutes($file, $config['locales'], $cleanConfig);
            $collection->addCollection($subCollection);
        }
    }

    private function importRoutes(FileResource $currentFile, string $filenameGlob, array $commonConfig): RouteCollection
    {
        $this->setCurrentDir(dirname($currentFile->getResource()));
        $imported = $this->import($filenameGlob, null, false, basename($currentFile->getResource()));
        if (!is_array($imported)) {
            $imported = [$imported];
        }

        $collection = new RouteCollection();

        foreach ($imported as $subCollection) {
            /** @var RouteCollection $subCollection */
            if (isset($commonConfig['path'])) {
                $subCollection->addPrefix($commonConfig['path']);
                $subCollection->addNamePrefix(trim($commonConfig['path'], '/') . '/');
            }

            $this->mergeRouteHost($subCollection, $commonConfig);
            $this->mergeRouteCondition($subCollection, $commonConfig);
            $this->mergeRouteSchemas($subCollection, $commonConfig);
            $this->mergeRouteDefaults($subCollection, $commonConfig);
            $this->mergeRouteRequirements($subCollection, $commonConfig);
            $this->mergeRouteOptions($subCollection, $commonConfig);
            $collection->addCollection($subCollection);
        }

        return $collection;
    }

    private function createGroupRoutes(FileResource $file, array $routes, array $groupConfig): RouteCollection
    {
        $collection = new RouteCollection();

        foreach ($routes as $config) {
            $this->parseDefinition($collection, $file, $config, $groupConfig);
        }

        return $collection;
    }

    private function createMethodsRoutes(FileResource $file, $methods, array $commonConfig): RouteCollection
    {
        Assert::definitionWithMethodsSpecification($methods, $commonConfig);
        $collection = new RouteCollection();

        foreach ($methods as $method => $config) {
            Assert::methodDefinition($config);
            $config['defaults']['_method'] = strtoupper($method);
            $this->parseDefinition($collection, $file, $config, $commonConfig);
        }

        return $collection;
    }

    private function createLocalizedRoutes(FileResource $file, array $localizedUrlTemplates, array $commonConfig): RouteCollection
    {
        if (!isset($commonConfig['path'])) {
            throw new InvalidArgumentException('Missing canonical path for localized routes.');
        }

        $commonConfig['defaults']['_canonical_route'] = $commonConfig['path'];
        unset($commonConfig['path']);

        $collection = new RouteCollection();

        foreach ($localizedUrlTemplates as $locale => $urlTemplate) {
            $config['path'] = $urlTemplate;
            $config['defaults']['_locale'] = $locale;
            $this->parseDefinition($collection, $file, $config, $commonConfig);
        }

        return $collection;
    }

    private function addRoute(RouteCollection $collection, array $config): void
    {
        $defaults = $config['defaults'] ?? [];
        $extraKeys = array_diff_key($config, YamlFileLoader::SUPPORTED_KEYS, $defaults);

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

        $route->addDefaults($extraKeys);

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

        $collection->add($routeName, $route);
    }

    /**
     * @param Route|RouteCollection $routeOrCollection
     * @param array $config
     */
    private function mergeRouteHost($routeOrCollection, array $config): void
    {
        if (isset($config['host'])) {
            $routeOrCollection->setHost($config['host']);
        }
    }

    /**
     * @param Route|RouteCollection $routeOrCollection
     * @param array $config
     */
    private function mergeRouteCondition($routeOrCollection, array $config): void
    {
        if (isset($config['condition'])) {
            $routeOrCollection->setCondition($config['condition']);
        }
    }

    /**
     * @param Route|RouteCollection $routeOrCollection
     * @param array $config
     */
    private function mergeRouteSchemas($routeOrCollection, array $config): void
    {
        if (isset($config['schemes'])) {
            $routeOrCollection->setSchemes($config['schemes']);
        }
    }

    /**
     * @param Route|RouteCollection $routeOrCollection
     * @param array $config
     */
    private function mergeRouteDefaults($routeOrCollection, array $config): void
    {
        if (isset($config['defaults'])) {
            $routeOrCollection->addDefaults($config['defaults']);

            if (isset($config['defaults']['_allowed_methods'])) {
                $routeOrCollection->setMethods($config['defaults']['_allowed_methods']);
            }
        }
    }

    /**
     * @param Route|RouteCollection $routeOrCollection
     * @param array $config
     */
    private function mergeRouteRequirements($routeOrCollection, array $config): void
    {
        if (isset($config['requirements'])) {
            $routeOrCollection->addRequirements($config['requirements']);
        }
    }

    /**
     * @param Route|RouteCollection $routeOrCollection
     * @param array $config
     */
    private function mergeRouteOptions($routeOrCollection, array $config): void
    {
        if (isset($config['options'])) {
            $routeOrCollection->addOptions($config['options']);
        }
    }
}
