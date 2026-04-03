<?php

declare(strict_types=1);

namespace Framework\Container;

/**
 * Read-only inspection descriptor for one registered service definition.
 */
final readonly class ContainerDefinitionDescriptor
{
    public function __construct(
        private string $id,
        private ContainerEntryOwner $owner,
        private string $origin,
        private ContainerServiceLifecycle $lifecycle,
        private ContainerDefinitionKind $definitionKind,
        private string $label,
        private bool $requiresContainer,
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function owner(): ContainerEntryOwner
    {
        return $this->owner;
    }

    public function origin(): string
    {
        return $this->origin;
    }

    public function lifecycle(): ContainerServiceLifecycle
    {
        return $this->lifecycle;
    }

    public function definitionKind(): ContainerDefinitionKind
    {
        return $this->definitionKind;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function requiresContainer(): bool
    {
        return $this->requiresContainer;
    }

    /**
     * @return array{
     *     entry_type: 'definition',
     *     id: string,
     *     owner: string,
     *     origin: string,
     *     lifecycle: string,
     *     definition_kind: string,
     *     label: string,
     *     requires_container: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'entry_type' => 'definition',
            'id' => $this->id(),
            'owner' => $this->owner()->value,
            'origin' => $this->origin(),
            'lifecycle' => $this->lifecycle()->value,
            'definition_kind' => $this->definitionKind()->value,
            'label' => $this->label(),
            'requires_container' => $this->requiresContainer(),
        ];
    }
}
