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
use function dirname;
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

        if (!is_string($filepath)) {
            throw new InvalidArgumentException(sprintf('Got "%s" but expected the string.', gettype($filepath)));
        }

        if (!stream_is_local($filepath)) {
            throw new InvalidArgumentException(sprintf('This is not a local file "%s".', $filepath));
        }

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
            $this->parse($collection, $config, $filename, $filepath);
        }

        return $collection;
    }

    public function supports($resource, $type = null): bool
    {
        return is_string($resource) && in_array(pathinfo($resource, PATHINFO_EXTENSION), ['yml', 'yaml'], true) && !$type;
    }

    public function parse(RouteCollection $collection, $config, string $filename, string $filepath): void
    {
        $this->assertConfigItem($filename, $config);

        if (isset($config['resource'])) {
            $this->parseImport($collection, $config, $filename, $filepath);
        } elseif (isset($config['group'])) {
            $this->parseGroup($collection, $config, $config['group']);
        } elseif (isset($config['methods'])) {
            $this->parseMethodRoutes($collection, $config, $config['methods']);
        } elseif (isset($config['locales'])) {
            $this->parseLocaleRoutes($collection, $config, $config['locales']);
        } else {
            $this->parseRoute($collection, $config);
        }
    }

    private function assertConfigItem(string $filename, $config): void
    {
        if (!is_array($config)) {
            throw new InvalidArgumentException(sprintf('The each definition in "%s" must be a YAML array.', $filename));
        }

        if ($extraKeys = array_diff(array_keys($config), self::SUPPORTED_KEYS)) {
            throw new InvalidArgumentException(
                sprintf(
                    'The routing file "%s" contains unsupported keys: "%s". Expected one of: "%s".',
                    $filename,
                    implode('", "', $extraKeys),
                    implode('", "', self::SUPPORTED_KEYS)
                )
            );
        }

        if (isset($config['resource'], $config['group'])) {
            throw new InvalidArgumentException(
                sprintf(
                    'The routing file "%s" must not specify both the "resource" key and the "group" key.',
                    $filename
                )
            );
        }

        if (isset($config['path']) && is_array($config['path'])) {
            throw new InvalidArgumentException('The path should be a string.');
        }

        if (isset($config['methods'])) {
            if (isset($config['defaults']['_allowed_methods'])) {
                throw new InvalidArgumentException(
                    sprintf('The definition with the "methods" in "%s" must not specify "_allowed_methods".', $filename)
                );
            }

            if (!is_array($config['methods'])) {
                throw new InvalidArgumentException(sprintf('The definition of the "methods" in "%s" must be a YAML array.', $filename));
            }

            if ($extraMethods = array_diff(array_map('strtoupper', array_keys($config['methods'])), self::SUPPORTED_METHODS)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'The routing file "%s" contains unsupported methods definition: "%s". Expected one of: "%s".',
                        $filename,
                        implode('", "', $extraMethods),
                        implode('", "', self::SUPPORTED_METHODS)
                    )
                );
            }

            foreach ($config['methods'] as $method => $methodConfig) {
                if (isset($methodConfig['path'])) {
                    throw new InvalidArgumentException(
                        sprintf('The definition of the "methods" in "%s" must not specify "path".', $filename)
                    );
                }

                if (isset($methodConfig['defaults']['_allowed_methods'])) {
                    throw new InvalidArgumentException(
                        sprintf('The definition of the "methods" in "%s" must not specify "_allowed_methods".', $filename)
                    );
                }
            }
        }
    }

    private function parseImport(RouteCollection $collection, array $commonConfig, string $filename, string $filepath): void
    {
        $routePrototype = $this->createRoute($commonConfig);

        $this->setCurrentDir(dirname($filepath));

        /** @var RouteCollection[] $imported */
        $imported = $this->import($commonConfig['resource'], null, false, $filename);
        if (!is_array($imported)) {
            $imported = [$imported];
        }

        foreach ($imported as $subCollection) {
            $this->extendCollection($subCollection, $routePrototype);
            $collection->addCollection($subCollection);
        }
    }

    private function parseGroup(RouteCollection $collection, array $groupConfig, array $routes): void
    {
        $routePrototype = $this->createRoute($groupConfig);

        foreach ($routes as $config) {
            $route = clone $routePrototype;
            $this->extendRoute($route, $config);

            if (isset($config['path'])) {
                $route->setPath($routePrototype->getPath() . $config['path']);
            }

            $collection->add($route->getPath(), $route);
        }
    }

    private function parseMethodRoutes(RouteCollection $collection, array $commonConfig, array $methods): void
    {
        $routePrototype = $this->createRoute($commonConfig);

        foreach ($methods as $method => $config) {
            $method = strtoupper($method);

            $route = clone $routePrototype;

            if ($config !== null) {
                $this->extendRoute($route, $config);
            }

            $route->setMethods($method === 'GET' ? ['GET', 'HEAD'] : [$method]);

            $collection->add($routePrototype->getPath() . '/' . strtolower($method), $route);
        }
    }

    private function parseLocaleRoutes(RouteCollection $collection, array $config, array $localizedUrlTemplates): void
    {
        $route = $this->createRoute($config);
        $canonicalUrlTemplate = $route->getPath();

        foreach ($localizedUrlTemplates as $locale => $urlTemplate) {
            $localizedRoute = clone $route;
            $localizedRoute->setDefault('_locale', $locale);
            $localizedRoute->setDefault('_canonical_route', $canonicalUrlTemplate);
            $localizedRoute->setPath($urlTemplate);

            $collection->add($canonicalUrlTemplate . '.' . $locale, $localizedRoute);
        }
    }

    private function parseRoute(RouteCollection $collection, array $config): void
    {
        $route = $this->createRoute($config);
        $collection->add($route->getPath(), $route);
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
        if ($routePrototype->getPath()) {
            $collection->addPrefix($routePrototype->getPath());
            $collection->addNamePrefix($routePrototype->getPath());
        }

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

        $collection->addDefaults($routePrototype->getDefaults());
        $collection->addRequirements($routePrototype->getRequirements());
        $collection->addOptions($routePrototype->getOptions());
    }
}
