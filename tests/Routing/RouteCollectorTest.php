<?php

declare(strict_types=1);

namespace Framework\Tests\Routing;

use Framework\Routing\RouteCollection;
use Framework\Routing\RouteCollector;
use Framework\Tests\Support\FrameworkTestCase;
use Framework\Tests\Support\Fixtures\GlobalOneMiddleware;
use Framework\Tests\Support\Fixtures\GlobalTwoMiddleware;
use Framework\Tests\Support\Fixtures\RouteMiddleware;

/** @psalm-suppress UnusedClass */
final class RouteCollectorTest extends FrameworkTestCase
{
    public function testRouteCollectorSupportsFluentRouteNaming(): void
    {
        $collector = new RouteCollector(new RouteCollection());
        $collector->get('/users/{id}', 'user-handler')->name('users.show');

        $routes = $collector->collection()->all();
        $route = array_shift($routes);

        self::assertNotNull($route);
        self::assertCount(0, $routes);
        self::assertSame('users.show', $route->name());
        self::assertSame('/users/{id}', $route->path());
    }

    public function testRouteCollectorAppliesNestedGroupPrefixesAndMiddleware(): void
    {
        $collector = new RouteCollector(new RouteCollection());

        $collector->group('/api', static function (RouteCollector $routes): void {
            $routes->group('/v1', static function (RouteCollector $routes): void {
                $routes->get('/users/{id}', 'user-handler', [RouteMiddleware::class])->name('api.users.show');
            }, [GlobalTwoMiddleware::class]);
        }, [GlobalOneMiddleware::class]);

        $routes = $collector->collection()->all();
        $route = array_shift($routes);

        self::assertNotNull($route);
        self::assertCount(0, $routes);
        self::assertSame('/api/v1/users/{id}', $route->path());
        self::assertSame('api.users.show', $route->name());
        self::assertSame(
            [GlobalOneMiddleware::class, GlobalTwoMiddleware::class, RouteMiddleware::class],
            $route->middleware()
        );
    }

    public function testRouteCollectorRestoresStateAfterEmptyGroup(): void
    {
        $collector = new RouteCollector(new RouteCollection());

        $collector->group('/api', static function (RouteCollector $routes): void {
            unset($routes);
        }, [GlobalOneMiddleware::class]);
        $collector->get('/health', 'health-handler');

        $routes = $collector->collection()->all();
        $route = array_shift($routes);

        self::assertNotNull($route);
        self::assertCount(0, $routes);
        self::assertSame('/health', $route->path());
        self::assertSame([], $route->middleware());
    }
}
