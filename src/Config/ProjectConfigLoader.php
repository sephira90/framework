<?php

declare(strict_types=1);

namespace Framework\Config;

use Framework\Foundation\Bootstrap\FrameworkCachePaths;

/**
 * Loads project configuration either from source files or from the explicit
 * framework cache when it already exists.
 */
final readonly class ProjectConfigLoader
{
    public function __construct(
        private EnvironmentLoader $environmentLoader = new EnvironmentLoader(),
    ) {
    }

    public function loadRuntime(string $basePath): Config
    {
        $this->environmentLoader->load($basePath);

        $cachePaths = new FrameworkCachePaths($basePath);

        if (is_file($cachePaths->configFile())) {
            return ConfigLoader::load($cachePaths->configFile());
        }

        return $this->loadSource($basePath);
    }

    public function loadSource(string $basePath): Config
    {
        $this->environmentLoader->load($basePath);

        return ConfigLoader::load($basePath . DIRECTORY_SEPARATOR . 'config');
    }
}
