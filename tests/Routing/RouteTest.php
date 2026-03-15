<?php

declare(strict_types=1);

namespace Framework\Tests\Routing;

use Framework\Routing\Route;
use Framework\Tests\Support\FrameworkTestCase;
use InvalidArgumentException;
use Nyholm\Psr7\Response;

/** @psalm-suppress UnusedClass */
final class RouteTest extends FrameworkTestCase
{
    public function testNormalizePathCollapsesRedundantSegments(): void
    {
        self::assertSame('/', Route::normalizePath(''));
        self::assertSame('/', Route::normalizePath(' / '));
        self::assertSame('/users', Route::normalizePath('///users//'));
        self::assertSame('/users/posts', Route::normalizePath(' users//posts/ '));
    }

    public function testRouteNormalizesMethodsAndStaticPaths(): void
    {
        $route = new Route(
            ['get', 'GET'],
            ' //health// ',
            static fn (): Response => new Response(200, [], 'ok')
        );

        self::assertSame(['GET'], $route->methods());
        self::assertSame('/health', $route->path());
        self::assertTrue($route->isStatic());
        self::assertTrue($route->matchesPath('/health/'));
        self::assertFalse($route->matchesPath('/health/check'));
        self::assertSame([], $route->extractParameters('/health'));
    }

    public function testDynamicRouteMatchesAndExtractsDecodedParameters(): void
    {
        $route = new Route(
            ['GET'],
            '/users/{id}/posts/{slug}',
            static fn (): Response => new Response(200, [], 'ok')
        );

        self::assertFalse($route->isStatic());
        self::assertTrue($route->matchesPath('/users/42/posts/hello%20world'));
        self::assertFalse($route->matchesPath('/users/42'));
        self::assertSame(
            ['id' => '42', 'slug' => 'hello world'],
            $route->extractParameters('/users/42/posts/hello%20world')
        );
    }

    public function testRouteRejectsDuplicateParameterNames(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('contains duplicate parameter names');

        new Route(
            ['GET'],
            '/users/{id}/posts/{id}',
            static fn (): Response => new Response(200, [], 'invalid')
        );
    }
}
