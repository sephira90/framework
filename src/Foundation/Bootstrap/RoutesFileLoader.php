<?php

declare(strict_types=1);

namespace Framework\Foundation\Bootstrap;

use Framework\Config\Config;
use Framework\Config\InvalidConfigurationException;
use Framework\Routing\RouteCollection;
use Framework\Routing\RouteCollector;
use Framework\Support\IsolatedFileRequirer;

/**
 * Загружает route collection из configured routes file.
 */
final class RoutesFileLoader
{
    public function load(string $basePath, Config $config): RouteCollection
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

        $collector = new RouteCollector(new RouteCollection());
        $registrar($collector);

        return $collector->collection();
    }
}
