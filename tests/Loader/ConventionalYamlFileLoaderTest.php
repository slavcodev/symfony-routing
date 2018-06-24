<?php
/**
 * This file is part of {@see https://github.com/slavcodev/ Slavcodev Projects}.
 */

declare(strict_types=1);

namespace Slavcodev\Symfony\Routing\Tests\Loader;

use Slavcodev\Symfony\Routing\Tests\TestCase;
use Slavcodev\Symfony\Routing\Loader\ConventionalYamlFileLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Routing\Route;

final class ConventionalYamlFileLoaderTest extends TestCase
{
    /**
     * @var ConventionalYamlFileLoader
     */
    private $loader;

    protected function setUp()
    {
        parent::setUp();
        $this->loader = new ConventionalYamlFileLoader(new FileLocator([__DIR__ . '/../Stubs']));
    }

    /**
     * @test
     */
    public function loadFile()
    {
        $routes = $this->loader->load('routing.yaml');
        self::assertCount(5, $routes);

        $getStatus = $routes->get('/api/status');
        self::assertNotNull($getStatus);
        self::assertSame('/api/status', $getStatus->getPath());
        self::assertSame('App\Controller\StatusApiController:handle', $getStatus->getDefault('_controller'));

        $getBlogPost = $routes->get('/api/posts/{id}');
        self::assertNotNull($getBlogPost);
        self::assertSame('/api/posts/{id}', $getBlogPost->getPath());
        self::assertSame('App\Controller\BlogApiController::get', $getBlogPost->getDefault('_controller'));
        self::assertNull($getBlogPost->getDefault('foo'));
        self::assertSame('bar', $getBlogPost->getDefault('bar'));

        $putBlogPost = $routes->get('/api/posts/{id}::put');
        self::assertNotNull($putBlogPost);
        self::assertSame('/api/posts/{id}', $putBlogPost->getPath());
        self::assertSame('App\Controller\BlogApiController::put', $putBlogPost->getDefault('_controller'));
        self::assertSame('foo', $putBlogPost->getDefault('foo'));
        self::assertNull($putBlogPost->getDefault('bar'));

        $getComments = $routes->get('/api/comments');
        self::assertNotNull($getComments);
        self::assertSame('/api/comments', $getComments->getPath());
        self::assertSame('App\Controller\CommentsApiController::handle', $getComments->getDefault('_controller'));

        $postComment = $routes->get('/api/comments::post');
        self::assertNotNull($postComment);
        self::assertSame('/api/comments', $postComment->getPath());
        self::assertSame('App\Controller\CommentsApiController::handle', $postComment->getDefault('_controller'));
    }

    /**
     * @test
     */
    public function loadResources()
    {
        $routes = $this->loader->load('routing-groups.yaml');
        self::assertCount(5, $routes);

        self::assertInstanceOf(Route::class, $routes->get('/new/api/status'));
        self::assertSame('/new/api/status', $routes->get('/new/api/status')->getPath());
        self::assertInstanceOf(Route::class, $routes->get('/new/api/posts/{id}'));
        self::assertSame('/new/api/posts/{id}', $routes->get('/new/api/posts/{id}')->getPath());
        self::assertInstanceOf(Route::class, $routes->get('/new/api/posts/{id}::put'));
        self::assertSame('/new/api/posts/{id}', $routes->get('/new/api/posts/{id}::put')->getPath());
        self::assertInstanceOf(Route::class, $routes->get('/new/api/comments'));
        self::assertSame('/new/api/comments', $routes->get('/new/api/comments')->getPath());
        self::assertInstanceOf(Route::class, $routes->get('/new/api/comments::post'));
        self::assertSame('/new/api/comments', $routes->get('/new/api/comments::post')->getPath());
    }
}
