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

        self::assertCount(1, $routes);
        self::assertSame('users.show', $routes[0]->name());
        self::assertSame('/users/{id}', $routes[0]->path());
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

        self::assertCount(1, $routes);
        self::assertSame('/api/v1/users/{id}', $routes[0]->path());
        self::assertSame('api.users.show', $routes[0]->name());
        self::assertSame(
            [GlobalOneMiddleware::class, GlobalTwoMiddleware::class, RouteMiddleware::class],
            $routes[0]->middleware()
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

        self::assertCount(1, $routes);
        self::assertSame('/health', $routes[0]->path());
        self::assertSame([], $routes[0]->middleware());
    }
}
