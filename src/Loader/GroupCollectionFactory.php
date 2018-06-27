<?php
/**
 * This file is part of {@see https://github.com/slavcodev/ Slavcodev Projects}.
 */

declare(strict_types=1);

namespace Slavcodev\Symfony\Routing\Loader;

use InvalidArgumentException;
use Symfony\Component\Routing\RouteCollection;

final class GroupCollectionFactory implements CollectionFactory
{
    private $routeFactory;

    private $collectionFactories;

    public function __construct(RouteFactory $routeFactory, array $collectionFactories = [])
    {
        $this->routeFactory = $routeFactory;

        foreach ($collectionFactories as $key => $collectionFactory) {
            $this->addCollectionFactory($key, $collectionFactory);
        }

        $this->addCollectionFactory('group', $this);
    }

    public function addCollectionFactory(string $key, CollectionFactory $factory): void
    {
        $this->collectionFactories[$key] = $factory;
    }

    public function create($routes, array $commonConfig): RouteCollection
    {
        if (!is_array($routes)) {
            throw new InvalidArgumentException('The definition of the "group" must be a YAML array.');
        }

        $collection = new RouteCollection();

        foreach ($routes as $config) {
            if (!is_array($config)) {
                throw new InvalidArgumentException('The each definition must be a YAML array.');
            }

            YamlFileLoader::mergeConfigs($config, $commonConfig);

            $collectionFactories = array_intersect_key($this->collectionFactories, $config);

            if (empty($collectionFactories)) {
                $route = $this->routeFactory->create($config);
                $collection->add($route->getDefault('_route'), $route);

                continue;
            }

            if (count($collectionFactories) !== 1) {
                throw new InvalidArgumentException('The definition must not specify more than one special "resource", "group", "methods" or "locale" keys.');
            }

            /** @var CollectionFactory $collectionFactory */
            $collectionFactory = reset($collectionFactories);
            $key = key($collectionFactories);
            $items = $config[$key];
            unset($config[$key]);

            $subCollection = $collectionFactory->create($items, $config);
            $collection->addCollection($subCollection);
        }

        return $collection;
    }
}
