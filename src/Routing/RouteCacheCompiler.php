<?php

declare(strict_types=1);

namespace Framework\Routing;

use Framework\Config\InvalidConfigurationException;
use Framework\Foundation\Bootstrap\FrameworkCacheMetadata;
use Framework\Foundation\Bootstrap\FrameworkCachePaths;
use Psr\Http\Server\MiddlewareInterface;

/**
 * Builds an explicit route cache snapshot from a precompiled route index.
 */
final class RouteCacheCompiler
{
    /**
     * @return array{
     *     cache: array{type: non-empty-string, version: positive-int},
     *     index: array{
     *         routes: list<array{
     *             methods: list<string>,
     *             path: string,
     *             handler: string,
     *             middleware: list<string>,
     *             name: string|null,
     *             compiled_path: array{
     *                 path: string,
     *                 matching_regex: non-empty-string,
     *                 parameters: list<array{
     *                     name: string,
     *                     placeholder: string,
     *                     constraint: string|null,
     *                     validationRegex: non-empty-string|null
     *                 }>,
     *                 static: bool,
     *                 segment_count: int,
     *                 first_literal_segment: string|null
     *             }
     *         }>,
     *         static_routes: array<string, array<string, int>>,
     *         dynamic_literal_buckets: array<int, array<string, list<int>>>,
     *         dynamic_wildcard_buckets: array<int, list<int>>,
     *         named_routes: array<string, int>
     *     }
     * }
     */
    public function compile(RouteIndex $index): array
    {
        try {
            $export = $index->export();
        } catch (\InvalidArgumentException $exception) {
            throw new InvalidConfigurationException($exception->getMessage(), 0, $exception);
        }

        foreach ($export['routes'] as $route) {
            if (!class_exists($route['handler'])) {
                throw new InvalidConfigurationException(sprintf(
                    'Route [%s] cannot be cached because handler [%s] is not a class-string.',
                    $route['path'],
                    $route['handler']
                ));
            }

            foreach ($route['middleware'] as $middleware) {
                if (!class_exists($middleware)) {
                    throw new InvalidConfigurationException(sprintf(
                        'Route [%s] cannot be cached because middleware [%s] is not a class-string.',
                        $route['path'],
                        $middleware
                    ));
                }

                if (!is_a($middleware, MiddlewareInterface::class, true)) {
                    throw new InvalidConfigurationException(sprintf(
                        'Route [%s] cannot be cached because middleware [%s] must implement %s.',
                        $route['path'],
                        $middleware,
                        MiddlewareInterface::class
                    ));
                }
            }
        }

        return [
            'cache' => FrameworkCacheMetadata::forType(FrameworkCacheMetadata::ROUTES_TYPE),
            'index' => $export,
        ];
    }

    public function render(RouteIndex $index): string
    {
        return "<?php\n\ndeclare(strict_types=1);\n\nreturn "
            . var_export($this->compile($index), true)
            . ";\n";
    }

    public function cacheFile(string $basePath): string
    {
        return (new FrameworkCachePaths($basePath))->routesFile();
    }
}
