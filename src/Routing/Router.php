<?php

declare(strict_types=1);

namespace Framework\Routing;

use Framework\Routing\Exception\UrlGenerationException;
use InvalidArgumentException;

/**
 * Выполняет method + path matching поверх зарегистрированной коллекции route'ов.
 *
 * Текущая стратегия:
 *
 * - static routes индексируются отдельно;
 * - static routes имеют приоритет над dynamic routes;
 * - результат матчинга всегда выражается через RouteMatch.
 */
final class Router
{
    /** @var array<string, array<string, Route>> */
    private array $staticRoutes = [];

    /** @var list<Route> */
    private array $dynamicRoutes = [];

    /** @var array<string, Route> */
    private array $namedRoutes = [];

    /**
     * Предкомпилирует коллекцию маршрутов в две структуры: static и dynamic.
     */
    public function __construct(RouteCollection $routes)
    {
        foreach ($routes->all() as $route) {
            $this->indexNamedRoute($route);

            if ($route->isStatic()) {
                foreach ($route->methods() as $method) {
                    $this->staticRoutes[$route->path()][$method] = $route;
                }

                continue;
            }

            $this->dynamicRoutes[] = $route;
        }
    }

    /**
     * Ищет маршрут по HTTP method и path.
     */
    public function match(string $method, string $path): RouteMatch
    {
        $normalizedMethod = strtoupper($method);
        $normalizedPath = Route::normalizePath($path);
        /** @var array<string, Route>&array{GET?: Route} $staticRoutes */
        $staticRoutes = $this->staticRoutes[$normalizedPath] ?? [];

        if (isset($staticRoutes[$normalizedMethod])) {
            return RouteMatch::found($staticRoutes[$normalizedMethod], []);
        }

        if ($normalizedMethod === 'HEAD' && isset($staticRoutes['GET'])) {
            return RouteMatch::found($staticRoutes['GET'], []);
        }

        if ($staticRoutes !== []) {
            return RouteMatch::methodNotAllowed(array_keys($staticRoutes));
        }

        $allowedMethods = [];

        foreach ($this->dynamicRoutes as $route) {
            if (!$route->matchesNormalizedPath($normalizedPath)) {
                continue;
            }

            if (in_array($normalizedMethod, $route->methods(), true)) {
                return RouteMatch::found($route, $route->extractParametersFromNormalizedPath($normalizedPath));
            }

            if ($normalizedMethod === 'HEAD' && in_array('GET', $route->methods(), true)) {
                return RouteMatch::found($route, $route->extractParametersFromNormalizedPath($normalizedPath));
            }

            $allowedMethods = [...$allowedMethods, ...$route->methods()];
        }

        if ($allowedMethods !== []) {
            return RouteMatch::methodNotAllowed($allowedMethods);
        }

        return RouteMatch::notFound();
    }

    /**
     * Генерирует path по имени маршрута и набору route parameters.
     *
     * @param array<string, string|int|float> $parameters
     *
     * @throws UrlGenerationException
     */
    public function url(string $name, array $parameters = []): string
    {
        $route = $this->namedRoutes[$name] ?? null;

        if ($route === null) {
            throw UrlGenerationException::unknownRouteName($name);
        }

        return $route->generatePath($parameters);
    }

    private function indexNamedRoute(Route $route): void
    {
        $name = $route->name();

        if ($name === null) {
            return;
        }

        if (isset($this->namedRoutes[$name])) {
            throw new InvalidArgumentException(sprintf('Route name [%s] is already registered.', $name));
        }

        $this->namedRoutes[$name] = $route;
    }
}
