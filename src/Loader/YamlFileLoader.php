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
use function dirname;
use function in_array;
use function is_array;
use function is_string;
use function ltrim;
use function pathinfo;
use function sprintf;
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

    private $routeFactory;

    public function __construct(FileLocatorInterface $locator)
    {
        parent::__construct($locator);
        $this->yamlParser = new Parser();
        $this->routeFactory = new RouteFactory();
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
        $this->setCurrentDir(dirname($file->getResource()));

        foreach ($parsedConfig as $config) {
            Assert::definition($config);
            $this->parseDefinition($collection, $config);
        }

        return $collection;
    }

    public function supports($resource, $type = null): bool
    {
        return is_string($resource) && in_array(pathinfo($resource, PATHINFO_EXTENSION), ['yml', 'yaml'], true) && !$type;
    }

    private function mergeConfigs(array &$config, array $defaultConfig)
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

    private function parseDefinition(RouteCollection $collection, $config)
    {
        $cleanConfig = array_diff_key($config, self::SPECIAL_KEYS);

        if ($cleanConfig === $config) {
            $route = $this->routeFactory->create($config);
            $collection->add($route->getDefault('_route'), $route);

            return;
        }

        if (count($config) - count($cleanConfig) > 1) {
            throw new InvalidArgumentException('The definition must not specify more than one special "resource", "group", "methods" or "locale" keys.');
        }

        if (isset($config['resource'])) {
            $subCollection = $this->importRoutes($config['resource'], $cleanConfig);
            $collection->addCollection($subCollection);
        } elseif (isset($config['group'])) {
            $subCollection = $this->createGroupRoutes($config['group'], $cleanConfig);
            $collection->addCollection($subCollection);
        } elseif (isset($config['methods'])) {
            $subCollection = $this->createMethodsRoutes($config['methods'], $cleanConfig);
            $collection->addCollection($subCollection);
        } elseif (isset($config['locales'])) {
            $subCollection = $this->createLocalizedRoutes($config['locales'], $cleanConfig);
            $collection->addCollection($subCollection);
        }
    }

    private function importRoutes(string $filenameGlob, array $config): RouteCollection
    {
        if (isset($config['type']) || isset($config['prefix']) || isset($config['name_prefix']) || isset($config['trailing_slash_on_root'])) {
            throw new InvalidArgumentException('The keys "type", "prefix", "name_prefix" and "trailing_slash_on_root" are deprecated.');
        }

        $imported = $this->import($filenameGlob);
        if (!is_array($imported)) {
            $imported = [$imported];
        }

        $collection = new RouteCollection();

        foreach ($imported as $subCollection) {
            /** @var RouteCollection $subCollection */
            if (isset($config['path'])) {
                $subCollection->addPrefix($config['path']);
                $subCollection->addNamePrefix(trim($config['path'], '/') . '/');
            }

            $this->mergeRouteHost($subCollection, $config);
            $this->mergeRouteCondition($subCollection, $config);
            $this->mergeRouteSchemas($subCollection, $config);
            $this->mergeRouteDefaults($subCollection, $config);
            $this->mergeRouteRequirements($subCollection, $config);
            $this->mergeRouteOptions($subCollection, $config);
            $collection->addCollection($subCollection);
        }

        return $collection;
    }

    private function createGroupRoutes(array $routes, array $groupConfig): RouteCollection
    {
        $collection = new RouteCollection();

        foreach ($routes as $config) {
            Assert::definition($config);
            self::mergeConfigs($config, $groupConfig);
            $this->parseDefinition($collection, $config);
        }

        return $collection;
    }

    private function createMethodsRoutes($methods, array $commonConfig): RouteCollection
    {
        Assert::definitionWithMethodsSpecification($methods, $commonConfig);
        if (!isset($commonConfig['path'])) {
            throw new InvalidArgumentException('Missing canonical path for localized routes.');
        }

        $commonConfig['defaults']['_canonical_route'] = $commonConfig['path'];

        $collection = new RouteCollection();

        foreach ($methods as $method => $config) {
            if ($config === null) {
                $config = [];
            }

            Assert::methodDefinition($config);
            $method = strtoupper($method);
            $config['defaults']['_method'] = $method;
            $config['defaults']['_allowed_methods'] = $method;
            self::mergeConfigs($config, $commonConfig);
            $this->parseDefinition($collection, $config);
        }

        return $collection;
    }

    private function createLocalizedRoutes(array $localizedUrlTemplates, array $commonConfig): RouteCollection
    {
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
            self::mergeConfigs($config, $commonConfig);
            $this->parseDefinition($collection, $config);
        }

        return $collection;
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
