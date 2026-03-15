<?php

declare(strict_types=1);

namespace Framework\Foundation\Bootstrap;

use Framework\Config\Config;
use Framework\Container\Container;
use Framework\Container\ContainerBuilder;

/**
 * Оркеструет fixed-order bootstrap lifecycle internal providers.
 *
 * Все providers проходят через register phase. В boot phase участвуют только те,
 * кто явно реализует BootableProviderInterface.
 */
final readonly class Bootstrapper
{
    /**
     * @param list<ServiceProviderInterface> $providers
     */
    public function __construct(
        private array $providers,
    ) {
    }

    public function bootstrap(string $basePath, Config $config): Container
    {
        $builder = new BootstrapBuilder($basePath, $config, new ContainerBuilder());

        foreach ($this->providers as $provider) {
            $provider->register($builder);
        }

        $container = $builder->containerBuilder()->build();
        $context = new BootstrapContext($basePath, $config, $container);

        foreach ($this->providers as $provider) {
            if (!$provider instanceof BootableProviderInterface) {
                continue;
            }

            $provider->boot($context);
        }

        return $container;
    }
}
