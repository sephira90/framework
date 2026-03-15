<?php

declare(strict_types=1);

namespace Framework\Tests\Routing;

use Framework\Routing\RouteCollection;
use Framework\Routing\RouteCollector;
use Framework\Routing\RouteMatchStatus;
use Framework\Routing\Router;
use Framework\Tests\Support\FrameworkTestCase;

/** @psalm-suppress UnusedClass */
final class RouterTest extends FrameworkTestCase
{
    public function testRouterMatchesStaticRoutesBeforeDynamicOnes(): void
    {
        $collector = new RouteCollector(new RouteCollection());
        $collector->get('/users/me', 'static-handler');
        $collector->get('/users/{id}', 'dynamic-handler');

        $router = new Router($collector->collection());

        $staticMatch = $router->match('GET', '/users/me');
        $dynamicMatch = $router->match('GET', '/users/42');

        self::assertSame(RouteMatchStatus::Found, $staticMatch->status());
        self::assertSame('/users/me', $staticMatch->route()->path());
        self::assertSame([], $staticMatch->parameters());

        self::assertSame(RouteMatchStatus::Found, $dynamicMatch->status());
        self::assertSame('/users/{id}', $dynamicMatch->route()->path());
        self::assertSame(['id' => '42'], $dynamicMatch->parameters());
    }

    public function testRouterReturnsMethodNotAllowedAndNotFound(): void
    {
        $collector = new RouteCollector(new RouteCollection());
        $collector->post('/users', 'create-user');

        $router = new Router($collector->collection());
        $methodNotAllowed = $router->match('GET', '/users');
        $notFound = $router->match('GET', '/missing');

        self::assertSame(RouteMatchStatus::MethodNotAllowed, $methodNotAllowed->status());
        self::assertSame(['POST'], $methodNotAllowed->allowedMethods());

        self::assertSame(RouteMatchStatus::NotFound, $notFound->status());
    }

    public function testRouterFallsBackFromHeadToGetForStaticAndDynamicRoutes(): void
    {
        $collector = new RouteCollector(new RouteCollection());
        $collector->get('/status', 'status-handler');
        $collector->get('/users/{id}', 'user-handler');

        $router = new Router($collector->collection());
        $staticMatch = $router->match('HEAD', '/status');
        $dynamicMatch = $router->match('HEAD', '/users/42');

        self::assertSame(RouteMatchStatus::Found, $staticMatch->status());
        self::assertSame('/status', $staticMatch->route()->path());
        self::assertSame([], $staticMatch->parameters());

        self::assertSame(RouteMatchStatus::Found, $dynamicMatch->status());
        self::assertSame('/users/{id}', $dynamicMatch->route()->path());
        self::assertSame(['id' => '42'], $dynamicMatch->parameters());
    }

    public function testRouterPrefersExplicitHeadRouteOverGetFallback(): void
    {
        $collector = new RouteCollector(new RouteCollection());
        $collector->get('/status', 'get-status-handler');
        $collector->match(['HEAD'], '/status', 'head-status-handler');

        $router = new Router($collector->collection());
        $match = $router->match('HEAD', '/status');

        self::assertSame(RouteMatchStatus::Found, $match->status());
        self::assertSame('head-status-handler', $match->route()->handler());
    }

    public function testRouteCollectorSupportsAllConvenienceMethods(): void
    {
        $collector = new RouteCollector(new RouteCollection());
        $collector->post('/posts', 'post-handler');
        $collector->put('/posts/{id}', 'put-handler');
        $collector->patch('/posts/{id}', 'patch-handler');
        $collector->delete('/posts/{id}', 'delete-handler');
        $collector->options('/posts', 'options-handler');
        $collector->any('/any', 'any-handler');

        $router = new Router($collector->collection());

        self::assertSame(RouteMatchStatus::Found, $router->match('POST', '/posts')->status());
        self::assertSame(RouteMatchStatus::Found, $router->match('PUT', '/posts/1')->status());
        self::assertSame(RouteMatchStatus::Found, $router->match('PATCH', '/posts/1')->status());
        self::assertSame(RouteMatchStatus::Found, $router->match('DELETE', '/posts/1')->status());
        self::assertSame(RouteMatchStatus::Found, $router->match('OPTIONS', '/posts')->status());
        self::assertSame(RouteMatchStatus::Found, $router->match('GET', '/any')->status());
        self::assertSame(RouteMatchStatus::Found, $router->match('DELETE', '/any')->status());
    }
}
