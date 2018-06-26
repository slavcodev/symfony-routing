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
        $expectedMessage = sprintf('Got "%s" but expected the string.', gettype([$filename]));

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
        $expectedMessage = sprintf('The file "%s" must contain a YAML array.', $this->loader->getLocator()->locate($filename));

        $this->expectExceptionObject(new InvalidArgumentException($expectedMessage));
        $this->loader->load($filename);
    }

    /**
     * @test
     */
    public function invalidItemFormat()
    {
        $this->expectExceptionObject(new InvalidArgumentException('The each definition must be a YAML array.'));
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
    public function thatDeprecatedKeysOfTheImportWontWork()
    {
        $this->expectExceptionObject(new InvalidArgumentException('The keys "type", "prefix", "name_prefix" and "trailing_slash_on_root" are deprecated.'));
        $this->loader->load('routing_imports_with_deprecated_keys.yaml');
    }

    /**
     * @test
     */
    public function thatAmbiguousControllerSettingWontWork()
    {
        $this->expectExceptionObject(new InvalidArgumentException('The definition must not specify both the "controller" key and the defaults key "_controller".'));
        $this->loader->load('routing_with_ambiguous_controller.yaml');
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
        $this->expectExceptionObject(new InvalidArgumentException('The path should be a string.'));
        $this->loader->load('routing_with_path_arrays.yaml');
    }

    /**
     * @test
     */
    public function thatOldMethodsFormatWontWork()
    {
        $this->expectExceptionObject(
            new InvalidArgumentException(
                sprintf(
                    'Unsupported methods definition: "%s". Expected one of: "%s".',
                    implode('", "', [0, 1]),
                    implode('", "', YamlFileLoader::SUPPORTED_METHODS)
                )
            )
        );
        $this->loader->load('routing_with_deprecated_methods_format.yaml');
    }

    /**
     * @test
     */
    public function thatMethodsDefinitionIsIterable()
    {
        $this->expectExceptionObject(new InvalidArgumentException('The definition of the "methods" must be a YAML array.'));
        $this->loader->load('routing_methods_iterable.yaml');
    }

    /**
     * @test
     */
    public function thatAmbiguousCommonMethodsAreNotAccepted()
    {
        $this->expectExceptionObject(new InvalidArgumentException('The definition with the "methods" must not specify "_allowed_methods".'));
        $this->loader->load('routing_ambiguous_common_methods.yaml');
    }

    /**
     * @test
     */
    public function thatMethodsDefinitionNotContainsPath()
    {
        $this->expectExceptionObject(new InvalidArgumentException('The definition of the "methods" must not specify "path".'));
        $this->loader->load('routing_methods_with_path.yaml');
    }

    /**
     * @test
     */
    public function thatAmbiguousMethodsAreNotAccepted()
    {
        $this->expectExceptionObject(new InvalidArgumentException('The definition of the "methods" must not specify "_allowed_methods".'));
        $this->loader->load('routing_ambiguous_methods.yaml');
    }

    /**
     * @test
     */
    public function requireCanonicalPathForLocalizedRoutes()
    {
        $this->expectExceptionObject(new InvalidArgumentException('Missing canonical path for localized routes.'));
        $this->loader->load('routing_locales_without_canonical.yaml');
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
            ['foo' => 'foo', 'bar' => 'bar', 'controller' => 'StatusController'],
            $routes->get('status')->getDefaults()
        );
        self::assertInstanceOf(Route::class, $routes->get('error'));
        self::assertSame(
            ['bar' => 'bar', 'foo' => 'foo', 'baz' => 'baz', 'controller' => 'ErrorController'],
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
        self::assertSame(['GET', 'HEAD'], $get->getMethods());
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
        self::assertSame(['GET', 'HEAD'], $get->getMethods());
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
     * test
     */
    public function loadingNestedRoutes()
    {
        $routes = $this->loader->load('routing_nested_items.yaml');
        self::assertCount(2, $routes);
        self::assertInstanceOf(Route::class, $routes->get('status'));
        self::assertSame(
            ['foo' => 'foo', 'bar' => 'bar', 'controller' => 'StatusController'],
            $routes->get('status')->getDefaults()
        );
        self::assertInstanceOf(Route::class, $routes->get('error'));
        self::assertSame(
            ['foo' => 'foo', 'bar' => 'bar', 'baz' => 'baz', 'controller' => 'ErrorController'],
            $routes->get('error')->getDefaults()
        );
    }
}
