<?php

declare(strict_types=1);

namespace Framework\Container;

/**
 * Immutable sidecar snapshot of the container registration graph.
 */
final readonly class ContainerInspectionSnapshot
{
    /**
     * @param array<string, ContainerDefinitionDescriptor> $definitions
     * @param array<string, ContainerAliasDescriptor> $aliases
     */
    public function __construct(
        private array $definitions,
        private array $aliases,
    ) {
    }

    public function definition(string $id): ?ContainerDefinitionDescriptor
    {
        return $this->definitions[$id] ?? null;
    }

    public function alias(string $id): ?ContainerAliasDescriptor
    {
        return $this->aliases[$id] ?? null;
    }

    public function entry(string $id): ContainerDefinitionDescriptor|ContainerAliasDescriptor|null
    {
        return $this->definition($id) ?? $this->alias($id);
    }

    /**
     * @return array{
     *     definitions: list<array{
     *         entry_type: 'definition',
     *         id: string,
     *         owner: string,
     *         origin: string,
     *         lifecycle: string,
     *         definition_kind: string,
     *         label: string,
     *         requires_container: bool
     *     }>,
     *     aliases: list<array{
     *         entry_type: 'alias',
     *         id: string,
     *         target: string,
     *         owner: string,
     *         origin: string
     *     }>
     * }
     */
    public function export(): array
    {
        return [
            'definitions' => array_values(array_map(
                static fn (ContainerDefinitionDescriptor $definition): array => $definition->toArray(),
                $this->definitions
            )),
            'aliases' => array_values(array_map(
                static fn (ContainerAliasDescriptor $alias): array => $alias->toArray(),
                $this->aliases
            )),
        ];
    }

    /**
     * @return array<string, string|bool>|null
     */
    public function exportEntry(string $id): ?array
    {
        $entry = $this->entry($id);

        if ($entry instanceof ContainerDefinitionDescriptor) {
            return $entry->toArray();
        }

        if ($entry instanceof ContainerAliasDescriptor) {
            return $entry->toArray();
        }

        return null;
    }
}
