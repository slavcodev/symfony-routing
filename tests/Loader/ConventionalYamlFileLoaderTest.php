<?php
/**
 * This file is part of {@see https://github.com/slavcodev/ Slavcodev Projects}.
 */

declare(strict_types=1);

namespace Slavcodev\Symfony\Routing\Tests\Loader;

use Slavcodev\Symfony\Routing\Tests\TestCase;
use Slavcodev\Symfony\Routing\Loader\ConventionalYamlFileLoader;
use Symfony\Component\Config\FileLocator;

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

        $putBlogPost = $routes->get('put::/api/posts/{id}');
        self::assertNotNull($putBlogPost);
        self::assertSame('/api/posts/{id}', $putBlogPost->getPath());
        self::assertSame('App\Controller\BlogApiController::put', $putBlogPost->getDefault('_controller'));
        self::assertSame('foo', $putBlogPost->getDefault('foo'));
        self::assertNull($putBlogPost->getDefault('bar'));

        $getComments = $routes->get('/api/comments');
        self::assertNotNull($getComments);
        self::assertSame('/api/comments', $getComments->getPath());
        self::assertSame('App\Controller\CommentsApiController::handle', $getComments->getDefault('_controller'));

        $postComment = $routes->get('post::/api/comments');
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

        self::assertNotNull($routes->get('/api/status'));
        self::assertNotNull($routes->get('/api/posts/{id}'));
        self::assertNotNull($routes->get('put::/api/posts/{id}'));
        self::assertNotNull($routes->get('/api/comments'));
        self::assertNotNull($routes->get('post::/api/comments'));
    }
}
