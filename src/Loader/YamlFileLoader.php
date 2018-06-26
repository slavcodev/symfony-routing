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
            Assert::definition($config);

            if ($extraKeys = array_diff_key($config, YamlFileLoader::SUPPORTED_KEYS)) {
                $config = array_intersect_key($config, YamlFileLoader::SUPPORTED_KEYS);
                if (!isset($config['defaults'])) {
                    $config['defaults'] = $extraKeys;
                } else {
                    $config['defaults'] = array_merge($config['defaults'], $extraKeys);
                }
            }

            if (isset($config['resource'])) {
                $importedRoutes = $this->importRoutes($file, $config['resource'], $config);
                $collection->addCollection($importedRoutes);
            } elseif (isset($config['group'])) {
                $groupRoutes = $this->createGroupRoutes($config['group'], $config);
                $collection->addCollection($groupRoutes);
            } elseif (isset($config['methods'])) {
                $methodRoutes = $this->createMethodsRoutes($config['methods'], $config);
                $collection->addCollection($methodRoutes);
            } elseif (isset($config['locales'])) {
                $localizedRoutes = $this->createLocalizedRoutes($config['locales'], $config);
                $collection->addCollection($localizedRoutes);
            } else {
                $route = $this->createRoute($config);
                $collection->add(trim($route->getPath(), '/'), $route);
            }
        }

        return $collection;
    }

    public function supports($resource, $type = null): bool
    {
        return is_string($resource) && in_array(pathinfo($resource, PATHINFO_EXTENSION), ['yml', 'yaml'], true) && !$type;
    }

    private function importRoutes(FileResource $currentFile, string $filenameGlob, array $commonConfig): RouteCollection
    {
        Assert::importDefinition($commonConfig);

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

    private function createGroupRoutes(array $routes, array $groupConfig): RouteCollection
    {
        $collection = new RouteCollection();
        $routePrototype = $this->createRoute($groupConfig);

        foreach ($routes as $config) {
            $route = clone $routePrototype;
            $this->mergeRouteHost($route, $config);
            $this->mergeRouteCondition($route, $config);
            $this->mergeRouteSchemas($route, $config);
            $this->mergeRouteDefaults($route, $config);
            $this->mergeRouteRequirements($route, $config);
            $this->mergeRouteOptions($route, $config);

            if (isset($config['path'])) {
                $route->setPath($routePrototype->getPath() . '/' . ltrim($config['path'], '/'));
            }

            $collection->add(trim($route->getPath(), '/'), $route);
        }

        return $collection;
    }

    private function createMethodsRoutes($methods, array $commonConfig): RouteCollection
    {
        Assert::definitionWithMethodsSpecification($methods, $commonConfig);

        $collection = new RouteCollection();
        $routePrototype = $this->createRoute($commonConfig);

        foreach ($methods as $method => $config) {
            Assert::methodDefinition($config);

            $method = strtoupper($method);

            $route = clone $routePrototype;

            if ($config !== null) {
                $this->mergeRouteHost($route, $config);
                $this->mergeRouteCondition($route, $config);
                $this->mergeRouteSchemas($route, $config);
                $this->mergeRouteDefaults($route, $config);
                $this->mergeRouteRequirements($route, $config);
                $this->mergeRouteOptions($route, $config);
            }

            $route->setMethods($method === 'GET' ? ['GET', 'HEAD'] : [$method]);

            $collection->add(trim($routePrototype->getPath(), '/') . '/' . strtolower($method), $route);
        }

        return $collection;
    }

    private function createLocalizedRoutes(array $localizedUrlTemplates, array $config): RouteCollection
    {
        $collection = new RouteCollection();
        $route = $this->createRoute($config);
        $canonicalUrlTemplate = $route->getPath();

        foreach ($localizedUrlTemplates as $locale => $urlTemplate) {
            $localizedRoute = clone $route;
            $localizedRoute->setDefault('_locale', $locale);
            $localizedRoute->setDefault('_canonical_route', $canonicalUrlTemplate);
            $localizedRoute->setPath($urlTemplate);

            $collection->add(trim($canonicalUrlTemplate, '/') . '.' . $locale, $localizedRoute);
        }

        return $collection;
    }

    private function createRoute(array $config): Route
    {
        return new Route(
            $config['path'] ?? '',
            $config['defaults'] ?? [],
            $config['requirements'] ?? [],
            $config['options'] ?? [],
            $config['host'] ?? null,
            $config['schemes'] ?? null,
            $config['defaults']['_allowed_methods'] ?? null,
            $config['condition'] ?? null
        );
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
