<?php

declare(strict_types=1);

namespace Framework\Container;

use Closure;
use Override;
use Psr\Container\ContainerInterface;
use Throwable;

/**
 * Минимальный explicit DI container.
 *
 * Контейнер не делает autowiring и не пытается "догадаться" о зависимостях.
 * Он разрешает только то, что было явно зарегистрировано через builder, и
 * сохраняет прозрачную модель: service definition -> resolution -> instance.
 */
final class Container implements ContainerInterface
{
    /** @var array<string, ServiceDefinition> */
    private array $definitions;

    /** @var array<string, string> */
    private array $aliases;

    /** @var array<string, mixed> */
    private array $instances = [];

    /** @var list<string> */
    private array $resolving = [];

    /**
     * @param array<string, ServiceDefinition> $definitions
     * @param array<string, string> $aliases
     */
    public function __construct(array $definitions, array $aliases)
    {
        $this->definitions = $definitions;
        $this->aliases = $aliases;
    }

    /**
     * Разрешает сервис по идентификатору с учётом alias'ов, singleton semantics
     * и защиты от циклических зависимостей.
     */
    #[Override]
    public function get(string $id): mixed
    {
        $resolvedId = $this->resolveAlias($id);

        if (array_key_exists($resolvedId, $this->instances)) {
            return $this->instances[$resolvedId];
        }

        $definition = $this->definitions[$resolvedId] ?? null;

        if ($definition === null) {
            throw new NotFoundException(sprintf('Service [%s] is not registered.', $id));
        }

        if (in_array($resolvedId, $this->resolving, true)) {
            $chain = implode(' -> ', [...$this->resolving, $resolvedId]);

            throw new ContainerException(sprintf(
                'Circular dependency detected while resolving [%s]. Chain: %s.',
                $id,
                $chain
            ));
        }

        $this->resolving[] = $resolvedId;

        try {
            if ($definition->shared) {
                return $this->instances[$resolvedId] = $this->invokeFactory(
                    $definition->factory,
                    $definition->requiresContainer
                );
            }

            return $this->invokeFactory($definition->factory, $definition->requiresContainer);
        } catch (NotFoundException | ContainerException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new ContainerException(sprintf(
                'Failed to resolve service [%s]: %s',
                $id,
                $exception->getMessage()
            ), 0, $exception);
        } finally {
            array_pop($this->resolving);
        }
    }

    /**
     * Проверяет, может ли контейнер разрешить сервис или alias.
     */
    #[Override]
    public function has(string $id): bool
    {
        try {
            $resolvedId = $this->resolveAlias($id);
        } catch (ContainerException) {
            return false;
        }

        return array_key_exists($resolvedId, $this->definitions) || array_key_exists($resolvedId, $this->instances);
    }

    /**
     * Разворачивает цепочку alias'ов до конечного service id.
     */
    private function resolveAlias(string $id): string
    {
        $resolvedId = $id;
        $seen = [];

        while (isset($this->aliases[$resolvedId])) {
            if (isset($seen[$resolvedId])) {
                $chain = implode(' -> ', [...array_keys($seen), $resolvedId]);

                throw new ContainerException(sprintf(
                    'Alias cycle detected while resolving [%s]. Chain: %s.',
                    $id,
                    $chain
                ));
            }

            $seen[$resolvedId] = true;
            $resolvedId = $this->aliases[$resolvedId];
        }

        return $resolvedId;
    }

    /**
     * Вызывает service factory по invocation mode, предвычисленному builder'ом.
     */
    private function invokeFactory(Closure $factory, bool $requiresContainer): mixed
    {
        return $requiresContainer ? $factory($this) : $factory();
    }
}
