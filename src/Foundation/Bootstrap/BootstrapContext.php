<?php

declare(strict_types=1);

namespace Framework\Foundation\Bootstrap;

use Framework\Config\Config;
use Framework\Container\Container;

/**
 * Post-container bootstrap context.
 */
final readonly class BootstrapContext
{
    public function __construct(
        private string $basePath,
        private Config $config,
        private Container $container,
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

    public function container(): Container
    {
        return $this->container;
    }
}
