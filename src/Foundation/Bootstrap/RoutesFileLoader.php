<?php

declare(strict_types=1);

namespace Framework\Foundation\Bootstrap;

use Framework\Config\Config;
use Framework\Config\InvalidConfigurationException;
use Framework\Routing\RouteCollection;
use Framework\Routing\RouteCollector;

/**
 * Загружает route collection из configured routes file.
 */
final class RoutesFileLoader
{
    public function load(string $basePath, Config $config): RouteCollection
    {
        $relativeRoutesPath = $config->get('routes', 'routes/web.php');

        if (!is_string($relativeRoutesPath) || $relativeRoutesPath === '') {
            throw new InvalidConfigurationException('Configuration key [routes] must be a non-empty string.');
        }

        $routesPath = $basePath
            . DIRECTORY_SEPARATOR
            . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativeRoutesPath);

        if (!is_file($routesPath)) {
            throw new InvalidConfigurationException(sprintf('Routes file [%s] was not found.', $routesPath));
        }

        $registrar = $this->requireFile($routesPath);

        if (!is_callable($registrar)) {
            throw new InvalidConfigurationException(sprintf(
                'Routes file [%s] must return a callable registrar.',
                $routesPath
            ));
        }

        $collector = new RouteCollector(new RouteCollection());
        $registrar($collector);

        return $collector->collection();
    }

    /**
     * Изолирует scope routes file от локального состояния loader'а.
     */
    private function requireFile(string $path): mixed
    {
        return (static function (string $path): mixed {
            /** @psalm-suppress UnresolvableInclude */
            return require $path;
        })($path);
    }
}
