<?php
/**
 * This file is part of {@see https://github.com/slavcodev/ Slavcodev Projects}.
 */

declare(strict_types=1);

namespace Slavcodev\Symfony\Routing\Tests\Loader;

use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use Slavcodev\Symfony\Routing\Loader\YamlFileLoader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\Routing\Route;

/**
 * @method static assertCount(int $expectedCount, $haystack, string $message = '')
 * @method static assertInstanceOf(string $expected, $actual, string $message = '')
 */
class YamlFileLoaderTest extends TestCase
{
    /**
     * @var YamlFileLoader
     */
    private $loader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loader = new YamlFileLoader(new FileLocator([__DIR__ . '/../Stubs']));
    }

    /**
     * @test
     */
    public function thatLocatorReturnValidFilePath()
    {
        $filename = 'routing.yaml';
        /** @var FileLocatorInterface|MockObject $locator */
        $locator = $this->createMock(FileLocatorInterface::class);
        $locator->method('locate')->willReturn([$filename]);
        $loader = new YamlFileLoader($locator);
        $expectedMessage = sprintf('The %s must be a string.', 'config file');

        $this->expectExceptionObject(new InvalidArgumentException($expectedMessage));
        $loader->load($filename);
    }

    /**
     * @test
     */
    public function supportOnlyLocalFiles()
    {
        $filename = 'https://raw.githubusercontent.com/slavcodev/symfony-routing/master/tests/Stubs/routing.yaml';
        /** @var FileLocatorInterface|MockObject $locator */
        $locator = $this->createMock(FileLocatorInterface::class);
        $locator->method('locate')->willReturn($filename);
        $loader = new YamlFileLoader($locator);
        $expectedMessage = sprintf('This is not a local file "%s".', $filename);

        $this->expectExceptionObject(new InvalidArgumentException($expectedMessage));
        $loader->load($filename);
    }

    /**
     * @test
     */
    public function invalidFile()
    {
        $filename = 'routing_invalid_file.yaml';
        $expectedMessage = sprintf('The file "%s" does not contain valid YAML.', $this->loader->getLocator()->locate($filename));

        $this->expectExceptionObject(new InvalidArgumentException($expectedMessage));
        $this->loader->load($filename);
    }

    /**
     * @test
     */
    public function invalidFileFormat()
    {
        $filename = 'routing_invalid_file_format.yaml';
        $expectedMessage = sprintf('The group routes must be a YAML array.', $this->loader->getLocator()->locate($filename));

        $this->expectExceptionObject(new InvalidArgumentException($expectedMessage));
        $this->loader->load($filename);
    }

    /**
     * @test
     */
    public function invalidItemFormat()
    {
        $this->expectExceptionObject(new InvalidArgumentException('The route definition must be a YAML array.'));
        $this->loader->load('routing_with_invalid_item.yaml');
    }

    /**
     * @test
     */
    public function loaderSupportsOnlyYaml()
    {
        self::assertTrue($this->loader->supports('routing.yml'));
        self::assertTrue($this->loader->supports('routing.yaml'));
        self::assertFalse($this->loader->supports(null));
        self::assertFalse($this->loader->supports(1));
        self::assertFalse($this->loader->supports(true));
        self::assertFalse($this->loader->supports('routing.json'));
        self::assertFalse($this->loader->supports('routing.yaml', 'YAML'));
    }

    /**
     * @test
     */
    public function thatNoWayToUseBoreThanOneAggregate()
    {
        $this->expectExceptionObject(new InvalidArgumentException('The definition must not specify more than one special "resource", "group", "methods" or "locale" keys.'));
        $this->loader->load('routing_with_both_resource_and_group.yaml');
    }

    /**
     * @test
     */
    public function thatPathMustBeString()
    {
        $this->expectExceptionObject(new InvalidArgumentException('The path must be a string.'));
        $this->loader->load('routing_with_path_arrays.yaml');
    }

    /**
     * @test
     */
    public function thatOldMethodsFormatWontWork()
    {
        $this->expectExceptionObject(new InvalidArgumentException('The method definition must be a YAML array.'));
        $this->loader->load('routing_with_deprecated_methods_format.yaml');
    }

    /**
     * @test
     */
    public function thatMethodsDefinitionIsIterable()
    {
        $this->expectExceptionObject(new InvalidArgumentException('The methods routes must be a YAML array.'));
        $this->loader->load('routing_methods_iterable.yaml');
    }

    /**
     * @test
     */
    public function thatAmbiguousCommonMethodsAreNotAccepted()
    {
        $this->expectExceptionObject(new InvalidArgumentException('The methods group definition must not contain "_allowed_methods".'));
        $this->loader->load('routing_ambiguous_common_methods.yaml');
    }

    /**
     * @test
     */
    public function thatMethodsDefinitionNotContainsPath()
    {
        $this->expectExceptionObject(new InvalidArgumentException('The method definition must not contain "path".'));
        $this->loader->load('routing_methods_with_path.yaml');
    }

    /**
     * @test
     */
    public function thatAmbiguousMethodsAreNotAccepted()
    {
        $this->expectExceptionObject(new InvalidArgumentException('The method definition must not contain "_allowed_methods".'));
        $this->loader->load('routing_ambiguous_methods.yaml');
    }

    /**
     * @test
     */
    public function requireCanonicalPathForMethodsRoutes()
    {
        $this->expectExceptionObject(new InvalidArgumentException('Missing canonical path for the methods group definition.'));
        $this->loader->load('routing_methods_without_canonical.yaml');
    }

    /**
     * @test
     */
    public function requireCanonicalPathForLocalizedRoutes()
    {
        $this->expectExceptionObject(new InvalidArgumentException('Missing canonical path for the localized paths.'));
        $this->loader->load('routing_locales_without_canonical.yaml');
    }

    /**
     * @test
     */
    public function requirePathForLocalizedRoute()
    {
        $this->expectExceptionObject(new InvalidArgumentException('The localized path must be a string.'));
        $this->loader->load('routing_locales_without_path.yaml');
    }

    /**
     * @test
     */
    public function invalidLocalizedRoutesFormat()
    {
        $this->expectExceptionObject(new InvalidArgumentException('The localized paths must be a YAML array.'));
        $this->loader->load('routing_locales_invalid_format.yaml');
    }

    /**
     * @test
     */
    public function invalidRoutesGroupFormat()
    {
        $this->expectExceptionObject(new InvalidArgumentException('The group routes must be a YAML array.'));
        $this->loader->load('routing_group_invalid_format.yaml');
    }

    /**
     * @test
     */
    public function thatRouteNameSameAsPath()
    {
        $routes = $this->loader->load('routing_with_name.yaml');
        self::assertCount(1, $routes);
        self::assertNull($routes->get('get_status'));
        self::assertInstanceOf(Route::class, $routes->get('status'));
        self::assertSame('/status', $routes->get('status')->getPath());
    }

    /**
     * @test
     */
    public function thatRouteCustomKeysWillAddToDefaults()
    {
        $routes = $this->loader->load('routing_item_with_custom_keys.yaml');
        self::assertCount(2, $routes);
        self::assertInstanceOf(Route::class, $routes->get('status'));
        self::assertSame(
            ['foo' => 'foo', 'bar' => 'bar', 'controller' => 'StatusController', '_route' => 'status'],
            $routes->get('status')->getDefaults()
        );
        self::assertInstanceOf(Route::class, $routes->get('error'));
        self::assertSame(
            ['bar' => 'bar', 'foo' => 'foo', 'baz' => 'baz', 'controller' => 'ErrorController', '_route' => 'error'],
            $routes->get('error')->getDefaults()
        );
    }

    /**
     * @test
     */
    public function importRoutingFromExternalFiles()
    {
        $filename = 'routing_imports.yaml';
        $routes = $this->loader->load($filename);
        $route = $routes->get('api/sub/status');
        self::assertCount(1, $routes);
        self::assertInstanceOf(Route::class, $route);
        self::assertSame('/api/sub/status', $route->getPath());
        // These properties from import override children routes
        self::assertSame('example.com', $route->getHost());
        self::assertSame(['https'], $route->getSchemes());
        self::assertSame(['GET', 'HEAD'], $route->getMethods());
        self::assertSame("context.getMethod() in ['GET', 'HEAD']", $route->getCondition());
        // These properties from import append to children routes
        self::assertSame(
            [
                'compiler_class' => 'Symfony\Component\Routing\RouteCompiler',
                'param' => 'value',
                'option' => 'value'
            ],
            $route->getOptions()
        );
        self::assertSame(
            [
                'id' => '\d+',
                'fields' => '\w+'
            ],
            $route->getRequirements()
        );
        self::assertSame(
            [
                'bar' => 'bar',
                '_allowed_methods' => ['GET', 'HEAD'],
                '_route' => 'status',
                '_controller' => 'FooController',
            ],
            $route->getDefaults()
        );
    }

    /**
     * @test
     */
    public function loadRoutingGrouped()
    {
        $routes = $this->loader->load('routing_group.yaml');
        self::assertCount(2, $routes);
        self::assertInstanceOf(Route::class, $routes->get('status/ok'));
        self::assertSame('/status/ok', $routes->get('status/ok')->getPath());
        self::assertInstanceOf(Route::class, $routes->get('status/error'));
        self::assertSame('/status/error', $routes->get('status/error')->getPath());
    }

    /**
     * @test
     */
    public function loadRoutingMethodsGroup()
    {
        $routes = $this->loader->load('routing_methods.yaml');
        self::assertCount(2, $routes);
        $get = $routes->get('status');
        $put = $routes->get('status/put');
        self::assertInstanceOf(Route::class, $get);
        self::assertInstanceOf(Route::class, $put);
        self::assertSame('/status', $get->getPath());
        self::assertSame('/status', $put->getPath());
        self::assertSame(['GET'], $get->getMethods());
        self::assertSame(['PUT'], $put->getMethods());
    }

    /**
     * @test
     */
    public function loadRoutingMethodsWithNoDetails()
    {
        $routes = $this->loader->load('routing_methods_with_no_details.yaml');
        self::assertCount(2, $routes);
        $get = $routes->get('status');
        $put = $routes->get('status/put');
        self::assertInstanceOf(Route::class, $get);
        self::assertInstanceOf(Route::class, $put);
        self::assertSame('/status', $get->getPath());
        self::assertSame('/status', $put->getPath());
        self::assertSame(['GET'], $get->getMethods());
        self::assertSame(['PUT'], $put->getMethods());
    }

    /**
     * @test
     */
    public function loadRoutingLocalized()
    {
        $routes = $this->loader->load('routing_locales.yaml');
        self::assertCount(2, $routes);

        $en = $routes->get('status.en');
        self::assertInstanceOf(Route::class, $en);
        self::assertSame('/status/en', $en->getPath());
        self::assertSame('en', $en->getDefault('_locale'));
        self::assertSame('/status', $en->getDefault('_canonical_route'));

        $es = $routes->get('status.es');
        self::assertInstanceOf(Route::class, $es);
        self::assertSame('/status/es', $es->getPath());
        self::assertSame('es', $es->getDefault('_locale'));
        self::assertSame('/status', $es->getDefault('_canonical_route'));
    }

    /**
     * @test
     */
    public function loadRoutingItem()
    {
        $routes = $this->loader->load('routing_items.yaml');
        self::assertCount(2, $routes);
        self::assertInstanceOf(Route::class, $routes->get('status'));
        self::assertSame('/status', $routes->get('status')->getPath());
        self::assertInstanceOf(Route::class, $routes->get('error'));
        self::assertSame('/error', $routes->get('error')->getPath());
    }

    /**
     * @test
     */
    public function loadingNestedRoutes()
    {
        $routes = $this->loader->load('routing_nested_items.yaml');
        self::assertCount(4, $routes);

        $getAlerts = $routes->get('api/alerts');
        $putAlerts = $routes->get('api/alerts/put');
        $statusOk = $routes->get('api/sub/status/ok');
        $statusError = $routes->get('api/sub/status/error');

        self::assertInstanceOf(Route::class, $getAlerts);
        self::assertSame('/api/alerts', $getAlerts->getPath());
        self::assertSame('baz', $getAlerts->getDefault('baz'));

        self::assertInstanceOf(Route::class, $putAlerts);
        self::assertSame('/api/alerts', $putAlerts->getPath());
        self::assertSame('ApiStatusController', $putAlerts->getDefault('controller'));

        self::assertInstanceOf(Route::class, $statusOk);
        self::assertSame('/api/sub/status/ok', $statusOk->getPath());
        self::assertSame(['GET'], $statusError->getDefault('_allowed_methods'));

        self::assertInstanceOf(Route::class, $statusError);
        self::assertSame('/api/sub/status/error', $statusError->getPath());
        // On import resource the common config for imported routes is more priority,
        // thus route schemes `['https']` is overridden with common schemes.
        self::assertSame(['https', 'http'], $statusError->getSchemes());
    }
}
