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
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Yaml;
use function dirname;
use function in_array;
use function is_array;
use function is_string;
use function pathinfo;
use function sprintf;
use function trim;

final class YamlFileLoader extends FileLoader implements CollectionFactory
{
    private $yamlParser;

    private $routeFactory;

    private $collectionFactory;

    public function __construct(FileLocatorInterface $locator)
    {
        parent::__construct($locator);
        $this->yamlParser = new Parser();
        $this->routeFactory = new RouteFactory();
        $this->collectionFactory = new GroupCollectionFactory(
            $this->routeFactory,
            [
                'resource' => $this,
                'locales' => new LocalizedRoutesFactory($this->routeFactory),
                'methods' => new MethodsRoutesFactory($this->routeFactory),
            ]
        );
    }

    public function load($filename, $type = null): RouteCollection
    {
        $filepath = $this->locator->locate($filename);

        Assert::isString($filepath, 'config file');
        Assert::isLocalStream($filepath);

        $file = new FileResource($filepath);
        $this->setCurrentDir(dirname($file->getResource()));

        try {
            $parsedConfig = $this->yamlParser->parseFile($file->getResource(), Yaml::PARSE_CONSTANT);
        } catch (ParseException $e) {
            throw new InvalidArgumentException(sprintf('The file "%s" does not contain valid YAML.', $filepath), 0, $e);
        }

        $collection = $this->collectionFactory->createRouteCollection($parsedConfig, []);
        $collection->addResource($file);

        return $collection;
    }

    public function supports($resource, $type = null): bool
    {
        return is_string($resource) && in_array(pathinfo($resource, PATHINFO_EXTENSION), ['yml', 'yaml'], true) && !$type;
    }

    public function createRouteCollection($filenameGlob, array $config): RouteCollection
    {
        $imported = $this->import($filenameGlob);
        if (!is_array($imported)) {
            $imported = [$imported];
        }

        $collection = new RouteCollection();

        foreach ($imported as $subCollection) {
            $this->mergeRoutePath($subCollection, $config);
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

    private function mergeRoutePath(RouteCollection $collection, array $config): void
    {
        if (isset($config['path'])) {
            /** @var mixed $route */
            $route = trim($config['path'], '/') . '/';
            $collection->addPrefix($config['path']);
            $collection->addNamePrefix($route);
        }
    }

    private function mergeRouteHost(RouteCollection $collection, array $config): void
    {
        if (isset($config['host'])) {
            $collection->setHost($config['host']);
        }
    }

    private function mergeRouteCondition(RouteCollection $collection, array $config): void
    {
        if (isset($config['condition'])) {
            $collection->setCondition($config['condition']);
        }
    }

    private function mergeRouteSchemas(RouteCollection $collection, array $config): void
    {
        if (isset($config['schemes'])) {
            $collection->setSchemes($config['schemes']);
        }
    }

    private function mergeRouteDefaults(RouteCollection $collection, array $config): void
    {
        if (isset($config['defaults'])) {
            $collection->addDefaults($config['defaults']);
            if (isset($config['defaults']['_allowed_methods'])) {
                $collection->setMethods($config['defaults']['_allowed_methods']);
            }
        }
    }

    private function mergeRouteRequirements(RouteCollection $collection, array $config): void
    {
        if (isset($config['requirements'])) {
            $collection->addRequirements($config['requirements']);
        }
    }

    private function mergeRouteOptions(RouteCollection $collection, array $config): void
    {
        if (isset($config['options'])) {
            $collection->addOptions($config['options']);
        }
    }
}
