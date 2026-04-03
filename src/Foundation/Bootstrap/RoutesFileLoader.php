<?php

declare(strict_types=1);

namespace Framework\Foundation\Bootstrap;

use Framework\Config\Config;
use Framework\Config\InvalidConfigurationException;
use Framework\Routing\RouteIndex;
use Framework\Routing\RouteCollector;
use Framework\Support\IsolatedFileRequirer;

/**
 * Загружает route collection из configured routes file.
 */
final class RoutesFileLoader
{
    public function load(string $basePath, Config $config): RouteIndex
    {
        $cachePaths = new FrameworkCachePaths($basePath);

        if (is_file($cachePaths->routesFile())) {
            return $this->loadCachedIndex($cachePaths->routesFile());
        }

        return $this->loadSource($basePath, $config);
    }

    public function loadSource(string $basePath, Config $config): RouteIndex
    {
        $routesPath = ProjectFileResolver::resolveConfiguredFile(
            $config,
            'routes',
            'routes/web.php',
            $basePath,
            'Routes file'
        );
        $registrar = IsolatedFileRequirer::require($routesPath);

        if (!is_callable($registrar)) {
            throw new InvalidConfigurationException(sprintf(
                'Routes file [%s] must return a callable registrar.',
                $routesPath
            ));
        }

        $collector = new RouteCollector(new \Framework\Routing\RouteCollection());
        $registrar($collector);

        return RouteIndex::fromRouteCollection($collector->collection());
    }

    private function loadCachedIndex(string $path): RouteIndex
    {
        $cache = IsolatedFileRequirer::require($path);

        if (!is_array($cache)) {
            throw new InvalidConfigurationException(sprintf(
                'Routes cache file [%s] must return an array.',
                $path
            ));
        }

        try {
            /** @var array{cache?: mixed, index?: mixed} $cache */
            FrameworkCacheMetadata::assertValid(
                $cache['cache'] ?? null,
                FrameworkCacheMetadata::ROUTES_TYPE,
                $path
            );

            $index = $cache['index'] ?? null;

            if (!is_array($index)) {
                throw new InvalidConfigurationException(sprintf(
                    'Routes cache file [%s] must contain a route index payload.',
                    $path
                ));
            }

            /** @var array{
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
             * } $index
             */
            return RouteIndex::fromExport($index);
        } catch (\Throwable $throwable) {
            throw new InvalidConfigurationException(sprintf(
                'Routes cache file [%s] is invalid: %s',
                $path,
                $throwable->getMessage()
            ), 0, $throwable);
        }
    }
}
