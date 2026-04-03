<?php

declare(strict_types=1);

namespace Framework\Foundation\Bootstrap;

use Framework\Container\ContainerBuilder;
use Framework\Container\ContainerEntryOwner;

/**
 * Applies already validated user-defined container configuration.
 *
 * Ownership contract is explicit: this registrar owns only application-defined
 * entries. If they collide with framework-owned ids or aliases, registration
 * must fail fast instead of relying on last-write-wins semantics.
 */
final class ConfiguredServicesRegistrar
{
    public function register(ContainerBuilder $builder, ConfiguredContainerConfig $config): void
    {
        foreach ($config->bindings() as $id => $definition) {
            $builder->bind($id, $definition, ContainerEntryOwner::Application, 'container.bindings');
        }

        foreach ($config->singletons() as $id => $definition) {
            $builder->singleton($id, $definition, ContainerEntryOwner::Application, 'container.singletons');
        }

        foreach ($config->aliases() as $id => $target) {
            $builder->alias($id, $target, ContainerEntryOwner::Application, 'container.aliases');
        }
    }
}
