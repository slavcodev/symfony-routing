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
use function array_diff;
use function array_keys;
use function array_map;
use function gettype;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function pathinfo;
use function sprintf;
use function stream_is_local;

final class YamlFileLoader extends FileLoader
{
    public const SUPPORTED_KEYS = [
        // Keys which specify parsing behavior
        'resource',
        'group',
        'methods',
        'locales',
        // Route definition keys
        'path',
        'host',
        'schemes',
        'defaults',
        'requirements',
        'options',
        'condition',
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

        $this->assertFileResource($filepath);

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
            $this->assertDefinition($config);

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

    private function assertFileResource($filepath): void
    {
        if (!is_string($filepath)) {
            throw new InvalidArgumentException(sprintf('Got "%s" but expected the string.', gettype($filepath)));
        }

        if (!stream_is_local($filepath)) {
            throw new InvalidArgumentException(sprintf('This is not a local file "%s".', $filepath));
        }
    }

    private function assertDefinition($config): void
    {
        if (!is_array($config)) {
            throw new InvalidArgumentException('The each definition must be a YAML array.');
        }

        if ($extraKeys = array_diff(array_keys($config), self::SUPPORTED_KEYS)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Definition contains unsupported keys: "%s". Expected one of: "%s".',
                    implode('", "', $extraKeys),
                    implode('", "', self::SUPPORTED_KEYS)
                )
            );
        }

        if (isset($config['path']) && is_array($config['path'])) {
            throw new InvalidArgumentException('The path should be a string.');
        }
    }

    private function assertImportDefinition($config): void
    {
        if (isset($config['group']) || isset($config['methods']) || isset($config['methods'])) {
            throw new InvalidArgumentException('The import definition must not specify the "group", "methods" or "locale" keys.');
        }
    }

    private function assertDefinitionWithMethodsSpecification($methods, $config): void
    {
        if (!is_array($methods)) {
            throw new InvalidArgumentException('The definition of the "methods" must be a YAML array.');
        }

        if (isset($config['defaults']['_allowed_methods'])) {
            throw new InvalidArgumentException('The definition with the "methods" must not specify "_allowed_methods".');
        }

        if ($extraMethods = array_diff(array_map('strtoupper', array_keys($methods)), self::SUPPORTED_METHODS)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Unsupported methods definition: "%s". Expected one of: "%s".',
                    implode('", "', $extraMethods),
                    implode('", "', self::SUPPORTED_METHODS)
                )
            );
        }
    }

    private function assertMethodDefinition($config): void
    {
        if (isset($config['path'])) {
            throw new InvalidArgumentException('The definition of the "methods" must not specify "path".');
        }

        if (isset($config['defaults']['_allowed_methods'])) {
            throw new InvalidArgumentException('The definition of the "methods" must not specify "_allowed_methods".');
        }
    }

    private function importRoutes(FileResource $currentFile, string $filenameGlob, array $commonConfig): RouteCollection
    {
        $this->assertImportDefinition($commonConfig);

        $collection = new RouteCollection();
        $routePrototype = $this->createRoute($commonConfig);

        $this->setCurrentDir(dirname($currentFile->getResource()));
        $imported = $this->import($filenameGlob, null, false, basename($currentFile->getResource()));
        if (!is_array($imported)) {
            $imported = [$imported];
        }

        foreach ($imported as $subCollection) {
            $this->extendCollection($subCollection, $routePrototype);
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
            $this->extendRoute($route, $config);

            if (isset($config['path'])) {
                $route->setPath($routePrototype->getPath() . $config['path']);
            }

            $collection->add(trim($route->getPath(), '/'), $route);
        }

        return $collection;
    }

    private function createMethodsRoutes($methods, array $commonConfig): RouteCollection
    {
        $this->assertDefinitionWithMethodsSpecification($methods, $commonConfig);

        $collection = new RouteCollection();
        $routePrototype = $this->createRoute($commonConfig);

        foreach ($methods as $method => $config) {
            $this->assertMethodDefinition($config);

            $method = strtoupper($method);

            $route = clone $routePrototype;

            if ($config !== null) {
                $this->extendRoute($route, $config);
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

    private function extendRoute(Route $route, array $config): void
    {
        if (isset($config['host'])) {
            $route->setHost($config['host']);
        }

        if (isset($config['condition'])) {
            $route->setCondition($config['condition']);
        }

        if (isset($config['schemes'])) {
            $route->setSchemes($config['schemes']);
        }

        if (isset($config['defaults']['_allowed_methods'])) {
            $route->setMethods($config['defaults']['_allowed_methods']);
        }

        if (isset($config['defaults'])) {
            $route->addDefaults($config['defaults']);
        }

        if (isset($config['requirements'])) {
            $route->addRequirements($config['requirements']);
        }

        if (isset($config['options'])) {
            $route->addOptions($config['options']);
        }
    }

    private function extendCollection(RouteCollection $collection, Route $routePrototype)
    {
        if ($routePrototype->getHost()) {
            $collection->setHost($routePrototype->getHost());
        }

        if ($routePrototype->getCondition()) {
            $collection->setCondition($routePrototype->getCondition());
        }

        if ($routePrototype->getSchemes()) {
            $collection->setSchemes($routePrototype->getSchemes());
        }

        if ($routePrototype->getMethods()) {
            $collection->setMethods($routePrototype->getMethods());
        }

        if ($routePrototype->getPath() !== '/') {
            $collection->addPrefix($routePrototype->getPath());
            $collection->addNamePrefix(trim($routePrototype->getPath(), '/') . '/');
        }

        $collection->addDefaults($routePrototype->getDefaults());
        $collection->addRequirements($routePrototype->getRequirements());
        $collection->addOptions($routePrototype->getOptions());
    }
}
