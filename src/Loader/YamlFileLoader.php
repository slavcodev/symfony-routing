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
use function gettype;
use function in_array;
use function is_array;
use function is_string;
use function ltrim;
use function pathinfo;
use function sprintf;
use function stream_is_local;
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

        $this->setCurrentDir(dirname($file->getResource()));

        $collection = $this->collectionFactory->create($parsedConfig, []);
        $collection->addResource($file);

        return $collection;
    }

    public function supports($resource, $type = null): bool
    {
        return is_string($resource) && in_array(pathinfo($resource, PATHINFO_EXTENSION), ['yml', 'yaml'], true) && !$type;
    }

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

    public function create($filenameGlob, array $config): RouteCollection
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

            if (isset($config['host'])) {
                $subCollection->setHost($config['host']);
            }
            if (isset($config['condition'])) {
                $subCollection->setCondition($config['condition']);
            }
            if (isset($config['schemes'])) {
                $subCollection->setSchemes($config['schemes']);
            }
            if (isset($config['defaults'])) {
                $subCollection->addDefaults($config['defaults']);

                if (isset($config['defaults']['_allowed_methods'])) {
                    $subCollection->setMethods($config['defaults']['_allowed_methods']);
                }
            }
            if (isset($config['requirements'])) {
                $subCollection->addRequirements($config['requirements']);
            }
            if (isset($config['options'])) {
                $subCollection->addOptions($config['options']);
            }

            $collection->addCollection($subCollection);
        }

        return $collection;
    }
}
