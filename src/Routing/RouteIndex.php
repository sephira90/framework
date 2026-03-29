<?php

declare(strict_types=1);

namespace Framework\Routing;

use Framework\Routing\Exception\UrlGenerationException;
use InvalidArgumentException;

/**
 * Precompiled routing index for hot-path matching and URL generation.
 *
 * It preserves the existing routing contract, but avoids scanning the full
 * dynamic route list on every request by narrowing candidates to deterministic
 * buckets first.
 */
final readonly class RouteIndex
{
    /**
     * @param list<Route> $routes
     * @param array<string, array<string, int>> $staticRoutes
     * @param array<int, array<string, list<int>>> $dynamicLiteralBuckets
     * @param array<int, list<int>> $dynamicWildcardBuckets
     * @param array<string, int> $namedRoutes
     */
    private function __construct(
        private array $routes,
        private array $staticRoutes,
        private array $dynamicLiteralBuckets,
        private array $dynamicWildcardBuckets,
        private array $namedRoutes,
    ) {
    }

    public static function fromRouteCollection(RouteCollection $routes): self
    {
        $indexedRoutes = [];
        $staticRoutes = [];
        $dynamicLiteralBuckets = [];
        $dynamicWildcardBuckets = [];
        $namedRoutes = [];

        foreach ($routes->all() as $index => $route) {
            $indexedRoutes[] = $route;
            self::indexNamedRoute($namedRoutes, $route, $index);

            if ($route->isStatic()) {
                foreach ($route->methods() as $method) {
                    $staticRoutes[$route->path()][$method] = $index;
                }

                continue;
            }

            $segmentCount = $route->segmentCount();
            $firstLiteralSegment = $route->firstLiteralSegment();

            if ($firstLiteralSegment === null) {
                $dynamicWildcardBuckets[$segmentCount][] = $index;
                continue;
            }

            $dynamicLiteralBuckets[$segmentCount][$firstLiteralSegment][] = $index;
        }

        return new self(
            $indexedRoutes,
            $staticRoutes,
            $dynamicLiteralBuckets,
            $dynamicWildcardBuckets,
            $namedRoutes
        );
    }

    /**
     * @param array{
     *     routes: list<array{
     *         methods: list<string>,
     *         path: string,
     *         handler: string,
     *         middleware: list<string>,
     *         name: string|null,
     *         compiled_path: array{
     *             path: string,
     *             matching_regex: non-empty-string,
     *             parameters: list<array{
     *                 name: string,
     *                 placeholder: string,
     *                 constraint: string|null,
     *                 validationRegex: non-empty-string|null
     *             }>,
     *             static: bool,
     *             segment_count: int,
     *             first_literal_segment: string|null
     *         }
     *     }>,
     *     static_routes: array<string, array<string, int>>,
     *     dynamic_literal_buckets: array<int, array<string, list<int>>>,
     *     dynamic_wildcard_buckets: array<int, list<int>>,
     *     named_routes: array<string, int>
     * } $data
     */
    public static function fromExport(array $data): self
    {
        $routes = [];

        foreach ($data['routes'] as $route) {
            $routes[] = Route::fromExport($route);
        }

        return new self(
            $routes,
            $data['static_routes'],
            $data['dynamic_literal_buckets'],
            $data['dynamic_wildcard_buckets'],
            $data['named_routes']
        );
    }

    public function match(string $method, string $path): RouteMatch
    {
        $normalizedMethod = strtoupper($method);
        $normalizedPath = Route::normalizePath($path);
        /** @var array<string, int>&array{GET?: int} $staticRoutes */
        $staticRoutes = $this->staticRoutes[$normalizedPath] ?? [];

        if (isset($staticRoutes[$normalizedMethod])) {
            return RouteMatch::found($this->routes[$staticRoutes[$normalizedMethod]], []);
        }

        if ($normalizedMethod === 'HEAD' && isset($staticRoutes['GET'])) {
            return RouteMatch::found($this->routes[$staticRoutes['GET']], []);
        }

        if ($staticRoutes !== []) {
            return RouteMatch::methodNotAllowed(array_keys($staticRoutes));
        }

        $allowedMethods = [];

        foreach ($this->candidateRouteIndexes($normalizedPath) as $routeIndex) {
            $route = $this->routes[$routeIndex];
            $parameters = $route->matchNormalizedPath($normalizedPath);

            if ($parameters === null) {
                continue;
            }

            if ($route->supportsMethod($normalizedMethod) || $route->supportsHeadFallbackFor($normalizedMethod)) {
                return RouteMatch::found($route, $parameters);
            }

            $allowedMethods = [...$allowedMethods, ...$route->methods()];
        }

        if ($allowedMethods !== []) {
            return RouteMatch::methodNotAllowed($allowedMethods);
        }

        return RouteMatch::notFound();
    }

    /**
     * @param array<string, string|int|float> $parameters
     *
     * @throws UrlGenerationException
     */
    public function url(string $name, array $parameters = []): string
    {
        $routeIndex = $this->namedRoutes[$name] ?? null;

        if ($routeIndex === null) {
            throw UrlGenerationException::unknownRouteName($name);
        }

        return $this->routes[$routeIndex]->generatePath($parameters);
    }

    /**
     * @return array{
     *     routes: list<array{
     *         methods: list<string>,
     *         path: string,
     *         handler: string,
     *         middleware: list<string>,
     *         name: string|null,
     *         compiled_path: array{
     *             path: string,
     *             matching_regex: non-empty-string,
     *             parameters: list<array{
     *                 name: string,
     *                 placeholder: string,
     *                 constraint: string|null,
     *                 validationRegex: non-empty-string|null
     *             }>,
     *             static: bool,
     *             segment_count: int,
     *             first_literal_segment: string|null
     *         }
     *     }>,
     *     static_routes: array<string, array<string, int>>,
     *     dynamic_literal_buckets: array<int, array<string, list<int>>>,
     *     dynamic_wildcard_buckets: array<int, list<int>>,
     *     named_routes: array<string, int>
     * }
     */
    public function export(): array
    {
        return [
            'routes' => array_map(
                static fn (Route $route): array => $route->export(),
                $this->routes
            ),
            'static_routes' => $this->staticRoutes,
            'dynamic_literal_buckets' => $this->dynamicLiteralBuckets,
            'dynamic_wildcard_buckets' => $this->dynamicWildcardBuckets,
            'named_routes' => $this->namedRoutes,
        ];
    }

    /**
     * @param array<string, int> $namedRoutes
     */
    private static function indexNamedRoute(array &$namedRoutes, Route $route, int $index): void
    {
        $name = $route->name();

        if ($name === null) {
            return;
        }

        if (isset($namedRoutes[$name])) {
            throw new InvalidArgumentException(sprintf('Route name [%s] is already registered.', $name));
        }

        $namedRoutes[$name] = $index;
    }

    /**
     * @return list<int>
     */
    private function candidateRouteIndexes(string $normalizedPath): array
    {
        if ($normalizedPath === '/') {
            return [];
        }

        $segments = explode('/', trim($normalizedPath, '/'));
        $segmentCount = count($segments);
        $firstSegment = $segments[0];

        if ($firstSegment === '') {
            return [];
        }

        $literalBucket = $this->dynamicLiteralBuckets[$segmentCount][$firstSegment] ?? [];
        $wildcardBucket = $this->dynamicWildcardBuckets[$segmentCount] ?? [];

        if ($literalBucket === []) {
            return $wildcardBucket;
        }

        if ($wildcardBucket === []) {
            return $literalBucket;
        }

        return $this->mergeSortedRouteIndexes($literalBucket, $wildcardBucket);
    }

    /**
     * @param list<int> $first
     * @param list<int> $second
     * @return list<int>
     */
    private function mergeSortedRouteIndexes(array $first, array $second): array
    {
        $merged = [];
        $firstIndex = 0;
        $secondIndex = 0;

        while (isset($first[$firstIndex]) && isset($second[$secondIndex])) {
            if ($first[$firstIndex] <= $second[$secondIndex]) {
                $merged[] = $first[$firstIndex];
                $firstIndex++;
                continue;
            }

            $merged[] = $second[$secondIndex];
            $secondIndex++;
        }

        while (isset($first[$firstIndex])) {
            $merged[] = $first[$firstIndex];
            $firstIndex++;
        }

        while (isset($second[$secondIndex])) {
            $merged[] = $second[$secondIndex];
            $secondIndex++;
        }

        return $merged;
    }
}
