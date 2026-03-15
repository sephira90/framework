<?php

declare(strict_types=1);

namespace Framework\Foundation\Bootstrap;

use Framework\Config\Config;
use Framework\Config\InvalidConfigurationException;
use Framework\Container\ContainerBuilder;

/**
 * Валидирует и применяет user-defined container configuration.
 */
final class ConfiguredServicesRegistrar
{
    public function register(ContainerBuilder $builder, Config $config): void
    {
        $containerConfig = $this->assertContainerConfig($config->get('container', []));
        $bindings = $this->assertDefinitionMap('bindings', $containerConfig['bindings'] ?? []);
        $singletons = $this->assertDefinitionMap('singletons', $containerConfig['singletons'] ?? []);
        $aliases = $this->assertAliasMap($containerConfig['aliases'] ?? []);

        foreach ($bindings as $id => $definition) {
            $builder->bind($id, $definition);
        }

        foreach ($singletons as $id => $definition) {
            $builder->singleton($id, $definition);
        }

        foreach ($aliases as $id => $target) {
            $builder->alias($id, $target);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function assertContainerConfig(mixed $config): array
    {
        if (!is_array($config)) {
            throw new InvalidConfigurationException('Configuration key [container] must be an array.');
        }

        /** @var array<string, mixed> $config */
        return $config;
    }

    /**
     * @return array<string, callable|string|object>
     */
    private function assertDefinitionMap(string $name, mixed $definitions): array
    {
        if (!is_array($definitions)) {
            throw new InvalidConfigurationException(sprintf(
                'Container configuration key [%s] must be an array.',
                $name
            ));
        }

        /** @var array<string, callable|string|object> $validated */
        $validated = [];

        foreach (array_keys($definitions) as $id) {
            if (!is_string($id) || $id === '') {
                throw new InvalidConfigurationException(sprintf(
                    'Container configuration key [%s] must use non-empty string identifiers.',
                    $name
                ));
            }

            $validated[$id] = $this->assertServiceDefinition($id, $definitions[$id]);
        }

        return $validated;
    }

    /**
     * @return array<string, string>
     */
    private function assertAliasMap(mixed $aliases): array
    {
        if (!is_array($aliases)) {
            throw new InvalidConfigurationException(
                'Container configuration key [aliases] must be an array.'
            );
        }

        $validated = [];

        foreach ($aliases as $id => $target) {
            if (!is_string($id) || $id === '') {
                throw new InvalidConfigurationException(
                    'Container aliases must use non-empty string identifiers.'
                );
            }

            if (!is_string($target) || $target === '') {
                throw new InvalidConfigurationException(sprintf(
                    'Container alias [%s] must point to a non-empty string target.',
                    $id
                ));
            }

            $validated[$id] = $target;
        }

        return $validated;
    }

    private function assertServiceDefinition(string $id, mixed $definition): callable|string|object
    {
        if (is_string($definition) || is_callable($definition) || is_object($definition)) {
            return $definition;
        }

        throw new InvalidConfigurationException(sprintf(
            'Container service [%s] must be defined by a class string, callable or object.',
            $id
        ));
    }
}
