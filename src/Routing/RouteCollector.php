<?php

declare(strict_types=1);

namespace Framework\Routing;

use Psr\Http\Server\MiddlewareInterface;

/**
 * API регистрации маршрутов на уровне routes file.
 */
final class RouteCollector
{
    public function __construct(
        private readonly RouteCollection $routes,
    ) {
    }

    /**
     * Регистрирует GET route.
     *
     * @param list<class-string<MiddlewareInterface>|MiddlewareInterface> $middleware
     */
    public function get(string $path, callable|string $handler, array $middleware = []): void
    {
        $this->match(['GET'], $path, $handler, $middleware);
    }

    /**
     * Регистрирует POST route.
     *
     * @param list<class-string<MiddlewareInterface>|MiddlewareInterface> $middleware
     */
    public function post(string $path, callable|string $handler, array $middleware = []): void
    {
        $this->match(['POST'], $path, $handler, $middleware);
    }

    /**
     * Регистрирует PUT route.
     *
     * @param list<class-string<MiddlewareInterface>|MiddlewareInterface> $middleware
     */
    public function put(string $path, callable|string $handler, array $middleware = []): void
    {
        $this->match(['PUT'], $path, $handler, $middleware);
    }

    /**
     * Регистрирует PATCH route.
     *
     * @param list<class-string<MiddlewareInterface>|MiddlewareInterface> $middleware
     */
    public function patch(string $path, callable|string $handler, array $middleware = []): void
    {
        $this->match(['PATCH'], $path, $handler, $middleware);
    }

    /**
     * Регистрирует DELETE route.
     *
     * @param list<class-string<MiddlewareInterface>|MiddlewareInterface> $middleware
     */
    public function delete(string $path, callable|string $handler, array $middleware = []): void
    {
        $this->match(['DELETE'], $path, $handler, $middleware);
    }

    /**
     * Регистрирует OPTIONS route.
     *
     * @param list<class-string<MiddlewareInterface>|MiddlewareInterface> $middleware
     */
    public function options(string $path, callable|string $handler, array $middleware = []): void
    {
        $this->match(['OPTIONS'], $path, $handler, $middleware);
    }

    /**
     * Регистрирует маршрут на все поддерживаемые методы `v0`.
     *
     * @param list<class-string<MiddlewareInterface>|MiddlewareInterface> $middleware
     */
    public function any(string $path, callable|string $handler, array $middleware = []): void
    {
        $this->match(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], $path, $handler, $middleware);
    }

    /**
     * Базовый метод регистрации маршрута.
     *
     * @param list<string> $methods
     * @param list<class-string<MiddlewareInterface>|MiddlewareInterface> $middleware
     */
    public function match(array $methods, string $path, callable|string $handler, array $middleware = []): void
    {
        $this->routes->add(new Route($methods, $path, $handler, $middleware));
    }

    public function collection(): RouteCollection
    {
        return $this->routes;
    }
}
