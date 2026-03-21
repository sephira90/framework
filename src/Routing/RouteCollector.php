<?php

declare(strict_types=1);

namespace Framework\Routing;

use Psr\Http\Server\MiddlewareInterface;

/**
 * API регистрации маршрутов на уровне routes file.
 *
 * @psalm-suppress PossiblyUnusedReturnValue
 */
final class RouteCollector
{
    private string $groupPrefix = '';

    /** @var list<class-string<MiddlewareInterface>|MiddlewareInterface> */
    private array $groupMiddleware = [];

    public function __construct(
        private readonly RouteCollection $routes,
    ) {
    }

    /**
     * Регистрирует группу маршрутов с общим path prefix и inherited middleware.
     *
     * @param callable(self): void $registrar
     * @param list<class-string<MiddlewareInterface>|MiddlewareInterface> $middleware
     */
    public function group(string $prefix, callable $registrar, array $middleware = []): void
    {
        $previousPrefix = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;

        $this->groupPrefix = self::joinPaths($this->groupPrefix, $prefix);
        $this->groupMiddleware = [...$this->groupMiddleware, ...$middleware];

        try {
            $registrar($this);
        } finally {
            $this->groupPrefix = $previousPrefix;
            $this->groupMiddleware = $previousMiddleware;
        }
    }

    /**
     * Регистрирует GET route.
     *
     * @param list<class-string<MiddlewareInterface>|MiddlewareInterface> $middleware
     *
     * Возвращаемый builder можно игнорировать, если маршруту не нужны
     * дополнительные metadata вроде имени.
     */
    public function get(string $path, callable|string $handler, array $middleware = []): RouteBuilder
    {
        return $this->register(['GET'], $path, $handler, $middleware);
    }

    /**
     * Регистрирует POST route.
     *
     * @param list<class-string<MiddlewareInterface>|MiddlewareInterface> $middleware
     * @psalm-suppress PossiblyUnusedReturnValue
     */
    public function post(string $path, callable|string $handler, array $middleware = []): RouteBuilder
    {
        return $this->register(['POST'], $path, $handler, $middleware);
    }

    /**
     * Регистрирует PUT route.
     *
     * @param list<class-string<MiddlewareInterface>|MiddlewareInterface> $middleware
     * @psalm-suppress PossiblyUnusedReturnValue
     */
    public function put(string $path, callable|string $handler, array $middleware = []): RouteBuilder
    {
        return $this->register(['PUT'], $path, $handler, $middleware);
    }

    /**
     * Регистрирует PATCH route.
     *
     * @param list<class-string<MiddlewareInterface>|MiddlewareInterface> $middleware
     * @psalm-suppress PossiblyUnusedReturnValue
     */
    public function patch(string $path, callable|string $handler, array $middleware = []): RouteBuilder
    {
        return $this->register(['PATCH'], $path, $handler, $middleware);
    }

    /**
     * Регистрирует DELETE route.
     *
     * @param list<class-string<MiddlewareInterface>|MiddlewareInterface> $middleware
     * @psalm-suppress PossiblyUnusedReturnValue
     */
    public function delete(string $path, callable|string $handler, array $middleware = []): RouteBuilder
    {
        return $this->register(['DELETE'], $path, $handler, $middleware);
    }

    /**
     * Регистрирует OPTIONS route.
     *
     * @param list<class-string<MiddlewareInterface>|MiddlewareInterface> $middleware
     * @psalm-suppress PossiblyUnusedReturnValue
     */
    public function options(string $path, callable|string $handler, array $middleware = []): RouteBuilder
    {
        return $this->register(['OPTIONS'], $path, $handler, $middleware);
    }

    /**
     * Регистрирует маршрут на все поддерживаемые методы `v0`.
     *
     * @param list<class-string<MiddlewareInterface>|MiddlewareInterface> $middleware
     * @psalm-suppress PossiblyUnusedReturnValue
     */
    public function any(string $path, callable|string $handler, array $middleware = []): RouteBuilder
    {
        return $this->register(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], $path, $handler, $middleware);
    }

    /**
     * Базовый метод регистрации маршрута.
     *
     * @param list<string> $methods
     * @param list<class-string<MiddlewareInterface>|MiddlewareInterface> $middleware
     * @psalm-suppress PossiblyUnusedReturnValue
     */
    public function match(array $methods, string $path, callable|string $handler, array $middleware = []): RouteBuilder
    {
        return $this->register($methods, $path, $handler, $middleware);
    }

    public function collection(): RouteCollection
    {
        return $this->routes;
    }

    /**
     * @param list<string> $methods
     * @param list<class-string<MiddlewareInterface>|MiddlewareInterface> $middleware
     */
    private function register(array $methods, string $path, callable|string $handler, array $middleware): RouteBuilder
    {
        $route = new Route(
            $methods,
            self::joinPaths($this->groupPrefix, $path),
            $handler,
            [...$this->groupMiddleware, ...$middleware]
        );
        $index = $this->routes->add($route);

        return new RouteBuilder($this->routes, $route, $index);
    }

    private static function joinPaths(string $prefix, string $path): string
    {
        $normalizedPrefix = trim($prefix);
        $normalizedPath = trim($path);

        if ($normalizedPrefix === '') {
            return Route::normalizePath($normalizedPath);
        }

        if ($normalizedPath === '') {
            return Route::normalizePath($normalizedPrefix);
        }

        return Route::normalizePath($normalizedPrefix . '/' . $normalizedPath);
    }
}
