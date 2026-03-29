<?php

declare(strict_types=1);

namespace Framework\Foundation\Bootstrap;

/**
 * Resolves deterministic framework-managed cache paths inside the project.
 */
final readonly class FrameworkCachePaths
{
    public function __construct(
        private string $basePath,
    ) {
    }

    public function directory(): string
    {
        return $this->basePath
            . DIRECTORY_SEPARATOR
            . 'var'
            . DIRECTORY_SEPARATOR
            . 'cache'
            . DIRECTORY_SEPARATOR
            . 'framework';
    }

    public function configFile(): string
    {
        return $this->directory() . DIRECTORY_SEPARATOR . 'config.php';
    }

    public function routesFile(): string
    {
        return $this->directory() . DIRECTORY_SEPARATOR . 'routes.php';
    }

    /**
     * @return list<string>
     */
    public function files(): array
    {
        return [
            $this->configFile(),
            $this->routesFile(),
        ];
    }
}
