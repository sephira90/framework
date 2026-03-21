<?php

declare(strict_types=1);

namespace Framework\Foundation\Bootstrap;

use Framework\Config\Config;
use Psr\Container\ContainerInterface;

/**
 * Post-container bootstrap context.
 */
final readonly class BootstrapContext
{
    public function __construct(
        private string $basePath,
        private Config $config,
        private ContainerInterface $container,
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

    public function container(): ContainerInterface
    {
        return $this->container;
    }
}
