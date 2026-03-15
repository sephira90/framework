<?php

declare(strict_types=1);

namespace Framework\Foundation\Bootstrap;

use Framework\Config\Config;
use Framework\Container\ContainerBuilder;

/**
 * Pre-container bootstrap context.
 */
final readonly class BootstrapBuilder
{
    public function __construct(
        private string $basePath,
        private Config $config,
        private ContainerBuilder $containerBuilder,
    ) {
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    public function config(): Config
    {
        return $this->config;
    }

    public function containerBuilder(): ContainerBuilder
    {
        return $this->containerBuilder;
    }
}
