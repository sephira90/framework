<?php

declare(strict_types=1);

namespace Framework\Tests\Routing;

use Framework\Routing\Exception\UrlGenerationException;
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
        self::assertTrue($route->matchesNormalizedPath('/health'));
        self::assertFalse($route->matchesNormalizedPath('/health/check'));
        self::assertSame([], $route->extractParametersFromNormalizedPath('/health'));
    }

    public function testDynamicRouteMatchesAndExtractsDecodedParameters(): void
    {
        $route = new Route(
            ['GET'],
            '/users/{id}/posts/{slug}',
            static fn (): Response => new Response(200, [], 'ok')
        );

        self::assertFalse($route->isStatic());
        self::assertTrue($route->matchesNormalizedPath('/users/42/posts/hello%20world'));
        self::assertFalse($route->matchesNormalizedPath('/users/42'));
        self::assertSame(
            ['id' => '42', 'slug' => 'hello world'],
            $route->extractParametersFromNormalizedPath('/users/42/posts/hello%20world')
        );
    }

    public function testConstrainedRouteMatchesOnlyValuesThatSatisfyItsParameterConstraints(): void
    {
        $route = new Route(
            ['GET'],
            '/users/{id:\d+}/posts/{slug:[a-z0-9-]+}',
            static fn (): Response => new Response(200, [], 'ok')
        );

        self::assertTrue($route->matchesNormalizedPath('/users/42/posts/hello-world'));
        self::assertFalse($route->matchesNormalizedPath('/users/forty-two/posts/hello-world'));
        self::assertFalse($route->matchesNormalizedPath('/users/42/posts/Hello%20World'));
        self::assertSame(
            ['id' => '42', 'slug' => 'hello-world'],
            $route->extractParametersFromNormalizedPath('/users/42/posts/hello-world')
        );
    }

    public function testRouteCanBeNamedAndGeneratePathFromParameters(): void
    {
        $route = (new Route(
            ['GET'],
            '/users/{id}/posts/{slug}',
            static fn (): Response => new Response(200, [], 'ok')
        ))->withName('users.posts.show');

        self::assertSame('users.posts.show', $route->name());
        self::assertSame('/users/42/posts/hello%20world', $route->generatePath([
            'id' => 42,
            'slug' => 'hello world',
        ]));
    }

    public function testRouteRejectsEmptyNamesAndMissingUrlParameters(): void
    {
        $route = new Route(
            ['GET'],
            '/users/{id}',
            static fn (): Response => new Response(200, [], 'ok')
        );

        try {
            $route->withName(' ');
            self::fail('Expected an exception for an empty route name.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('Route name must not be empty', $exception->getMessage());
        }

        $this->expectException(UrlGenerationException::class);
        $this->expectExceptionMessage('requires parameter [id]');

        $route->generatePath();
    }

    public function testRouteRejectsUnexpectedOrConstraintViolatingUrlParameters(): void
    {
        $route = new Route(
            ['GET'],
            '/users/{id:\d+}',
            static fn (): Response => new Response(200, [], 'ok')
        );

        try {
            $route->generatePath(['id' => 42, 'extra' => 'ignored']);
            self::fail('Expected an exception for unexpected route parameters.');
        } catch (UrlGenerationException $exception) {
            self::assertStringContainsString('does not define parameter(s) [extra]', $exception->getMessage());
        }

        $this->expectException(UrlGenerationException::class);
        $this->expectExceptionMessage('match constraint [\d+]');

        $route->generatePath(['id' => 'abc']);
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

    public function testRouteRejectsInvalidParameterSegmentsAndConstraints(): void
    {
        try {
            new Route(
                ['GET'],
                '/users/{id:}',
                static fn (): Response => new Response(200, [], 'invalid')
            );
            self::fail('Expected an exception for an invalid route parameter segment.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('invalid parameter segment', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('contains invalid constraint');

        new Route(
            ['GET'],
            '/users/{id:(}',
            static fn (): Response => new Response(200, [], 'invalid')
        );
    }
}
