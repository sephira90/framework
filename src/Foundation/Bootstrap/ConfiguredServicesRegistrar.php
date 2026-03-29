<?php

declare(strict_types=1);

namespace Framework\Foundation\Bootstrap;

use Framework\Container\ContainerBuilder;

/**
 * Applies already validated user-defined container configuration.
 */
final class ConfiguredServicesRegistrar
{
    public function register(ContainerBuilder $builder, ConfiguredContainerConfig $config): void
    {
        foreach ($config->bindings() as $id => $definition) {
            $builder->bind($id, $definition);
        }

        foreach ($config->singletons() as $id => $definition) {
            $builder->singleton($id, $definition);
        }

        foreach ($config->aliases() as $id => $target) {
            $builder->alias($id, $target);
        }
    }
}
