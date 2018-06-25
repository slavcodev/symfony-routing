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
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function pathinfo;
use function rtrim;
use function sprintf;
use function stream_is_local;

final class YamlFileLoader extends FileLoader
{
    public const SUPPORTED_KEYS = [
        // Keys which specify parsing behavior
        'resource',
        'group',
        'methods',
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
            $this->parseMethods($collection, $config, $config['methods']);
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

    private function parseImport(RouteCollection $collection, array $config, string $filename, string $filepath): void
    {
        $path = $config['path'] ?? '';
        $defaults = $config['defaults'] ?? [];
        $requirements = $config['requirements'] ?? [];
        $options = $config['options'] ?? [];
        $host = $config['host'] ?? null;
        $condition = $config['condition'] ?? null;
        $schemes = $config['schemes'] ?? null;
        $methods = $defaults['_allowed_methods'] ?? null;

        $this->setCurrentDir(dirname($filepath));

        /** @var RouteCollection[] $imported */
        $imported = $this->import($config['resource'], null, false, $filename);
        if (!is_array($imported)) {
            $imported = [$imported];
        }

        foreach ($imported as $subCollection) {
            if ($path) {
                $subCollection->addPrefix($path);
                $subCollection->addNamePrefix($path);
                $rootPath = (new Route($path))->getPath();

                foreach ($subCollection->all() as $route) {
                    if ($route->getPath() === $rootPath) {
                        $route->setPath(rtrim($rootPath, '/'));
                    }
                }
            }

            if ($host !== null) {
                $subCollection->setHost($host);
            }

            if ($condition !== null) {
                $subCollection->setCondition($condition);
            }

            if ($schemes !== null) {
                $subCollection->setSchemes($schemes);
            }

            if ($methods !== null) {
                $subCollection->setMethods($methods);
            }

            $subCollection->addDefaults($defaults);
            $subCollection->addRequirements($requirements);
            $subCollection->addOptions($options);

            $collection->addCollection($subCollection);
        }
    }

    private function parseGroup(RouteCollection $collection, array $config, array $subRoutes): void
    {
        $groupUrlTemplate = $config['path'] ?? '';
        $groupDefaults = $config['defaults'] ?? [];
        $groupRequirements = $config['requirements'] ?? [];
        $groupOptions = $config['options'] ?? [];
        $groupHost = $config['host'] ?? null;
        $groupCondition = $config['condition'] ?? null;
        $groupSchemes = $config['schemes'] ?? null;
        $groupMethods = $groupDefaults['_allowed_methods'] ?? null;

        foreach ($subRoutes as $subConfig) {
            $urlTemplate = $groupUrlTemplate . ($subConfig['path'] ?? '');
            $defaults = $subConfig['defaults'] ?? [];
            $requirements = $subConfig['requirements'] ?? [];
            $options = $subConfig['options'] ?? [];
            $host = $subConfig['host'] ?? null;
            $schemes = $subConfig['schemes'] ?? null;
            $condition = $subConfig['condition'] ?? null;
            $methods = $defaults['_allowed_methods'] ?? null;

            $subRoute = new Route($urlTemplate, $defaults, $requirements, $options, $host, $schemes, $methods, $condition);

            if ($groupHost !== null) {
                $subRoute->setHost($groupHost);
            }

            if ($groupCondition !== null) {
                $subRoute->setCondition($groupCondition);
            }

            if ($groupSchemes !== null) {
                $subRoute->setSchemes($groupSchemes);
            }

            if ($groupMethods !== null) {
                $subRoute->setMethods($groupMethods);
            }

            $subRoute->addDefaults($groupDefaults);
            $subRoute->addRequirements($groupRequirements);
            $subRoute->addOptions($groupOptions);

            $collection->add($urlTemplate, $subRoute);
        }
    }

    private function parseMethods(RouteCollection $collection, array $config, array $methodsRoutes): void
    {
        $groupUrlTemplate = $config['path'] ?? '';
        $groupDefaults = $config['defaults'] ?? [];
        $groupRequirements = $config['requirements'] ?? [];
        $groupOptions = $config['options'] ?? [];
        $groupHost = $config['host'] ?? null;
        $groupCondition = $config['condition'] ?? null;
        $groupSchemes = $config['schemes'] ?? null;

        foreach ($methodsRoutes as $method => $subConfig) {
            $method = strtoupper($method);
            $defaults = $subConfig['defaults'] ?? [];
            $requirements = $subConfig['requirements'] ?? [];
            $options = $subConfig['options'] ?? [];
            $host = $subConfig['host'] ?? null;
            $schemes = $subConfig['schemes'] ?? null;
            $condition = $subConfig['condition'] ?? null;
            $methods = $method === 'GET' ? ['GET', 'HEAD'] : [$method];

            $subRoute = new Route($groupUrlTemplate, $defaults, $requirements, $options, $host, $schemes, $methods, $condition);

            if ($groupHost !== null) {
                $subRoute->setHost($groupHost);
            }

            if ($groupCondition !== null) {
                $subRoute->setCondition($groupCondition);
            }

            if ($groupSchemes !== null) {
                $subRoute->setSchemes($groupSchemes);
            }

            $subRoute->addDefaults($groupDefaults);
            $subRoute->addRequirements($groupRequirements);
            $subRoute->addOptions($groupOptions);

            $collection->add($groupUrlTemplate . '/' . strtolower($method), $subRoute);
        }
    }

    private function parseRoute(RouteCollection $collection, array $config): void
    {
        $urlTemplate = $config['path'] ?? '';
        $defaults = $config['defaults'] ?? [];
        $requirements = $config['requirements'] ?? [];
        $options = $config['options'] ?? [];
        $host = $config['host'] ?? null;
        $condition = $config['condition'] ?? null;
        $schemes = $config['schemes'] ?? null;
        $methods = $defaults['_allowed_methods'] ?? null;

        $route = new Route($urlTemplate, $defaults, $requirements, $options, $host, $schemes, $methods, $condition);
        $collection->add($urlTemplate, $route);
    }
}
