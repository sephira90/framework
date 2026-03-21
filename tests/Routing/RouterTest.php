<?php

declare(strict_types=1);

namespace Framework\Tests\Routing;

use Framework\Routing\RouteCollection;
use Framework\Routing\RouteCollector;
use Framework\Routing\Exception\UrlGenerationException;
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

    public function testRouterNormalizesIncomingPathsBeforeMatchingRoutes(): void
    {
        $collector = new RouteCollector(new RouteCollection());
        $collector->get('/health', 'health-handler');
        $collector->get('/users/{id}', 'user-handler');

        $router = new Router($collector->collection());
        $staticMatch = $router->match('GET', ' //health// ');
        $dynamicMatch = $router->match('GET', ' ///users//42/ ');

        self::assertSame(RouteMatchStatus::Found, $staticMatch->status());
        self::assertSame('/health', $staticMatch->route()->path());

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

    public function testRouterAppliesRouteParameterConstraintsDuringMatching(): void
    {
        $collector = new RouteCollector(new RouteCollection());
        $collector->get('/users/{id:\d+}', 'user-handler');

        $router = new Router($collector->collection());
        $matched = $router->match('GET', '/users/42');
        $notFound = $router->match('GET', '/users/forty-two');

        self::assertSame(RouteMatchStatus::Found, $matched->status());
        self::assertSame(['id' => '42'], $matched->parameters());
        self::assertSame(RouteMatchStatus::NotFound, $notFound->status());
    }

    public function testRouterIncludesHeadInAllowedMethodsWhenGetIsSupported(): void
    {
        $collector = new RouteCollector(new RouteCollection());
        $collector->get('/status', 'status-handler');

        $router = new Router($collector->collection());
        $match = $router->match('DELETE', '/status');

        self::assertSame(RouteMatchStatus::MethodNotAllowed, $match->status());
        self::assertSame(['GET', 'HEAD'], $match->allowedMethods());
    }

    public function testRouterGeneratesUrlsFromNamedRoutes(): void
    {
        $collector = new RouteCollector(new RouteCollection());
        $collector->get('/status', 'status-handler')->name('status.show');
        $collector->get('/users/{id:\d+}/posts/{slug:[a-z0-9-]+}', 'post-handler')->name('users.posts.show');

        $router = new Router($collector->collection());

        self::assertSame('/status', $router->url('status.show'));
        self::assertSame('/users/42/posts/hello-world', $router->url('users.posts.show', [
            'id' => 42,
            'slug' => 'hello-world',
        ]));
    }

    public function testRouterRejectsUnexpectedParametersDuringUrlGeneration(): void
    {
        $collector = new RouteCollector(new RouteCollection());
        $collector->get('/users/{id:\d+}', 'user-handler')->name('users.show');

        $router = new Router($collector->collection());

        $this->expectException(UrlGenerationException::class);
        $this->expectExceptionMessage('does not define parameter(s) [extra]');

        $router->url('users.show', ['id' => 42, 'extra' => 'ignored']);
    }

    public function testRouterRejectsUnknownOrDuplicateNamedRoutes(): void
    {
        $collector = new RouteCollector(new RouteCollection());
        $collector->get('/status', 'status-handler')->name('status.show');

        $router = new Router($collector->collection());

        try {
            $router->url('missing.route');
            self::fail('Expected an exception for an unknown route name.');
        } catch (UrlGenerationException $exception) {
            self::assertStringContainsString('is not registered', $exception->getMessage());
        }

        $duplicateCollector = new RouteCollector(new RouteCollection());
        $duplicateCollector->get('/first', 'first-handler')->name('duplicate.name');
        $duplicateCollector->get('/second', 'second-handler')->name('duplicate.name');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('already registered');

        new Router($duplicateCollector->collection());
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
