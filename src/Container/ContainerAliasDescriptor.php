<?php

declare(strict_types=1);

namespace Framework\Container;

/**
 * Read-only inspection descriptor for one registered alias.
 */
final readonly class ContainerAliasDescriptor
{
    public function __construct(
        private string $id,
        private string $target,
        private ContainerEntryOwner $owner,
        private string $origin,
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function target(): string
    {
        return $this->target;
    }

    public function owner(): ContainerEntryOwner
    {
        return $this->owner;
    }

    public function origin(): string
    {
        return $this->origin;
    }

    /**
     * @return array{
     *     entry_type: 'alias',
     *     id: string,
     *     target: string,
     *     owner: string,
     *     origin: string
     * }
     */
    public function toArray(): array
    {
        return [
            'entry_type' => 'alias',
            'id' => $this->id(),
            'target' => $this->target(),
            'owner' => $this->owner()->value,
            'origin' => $this->origin(),
        ];
    }
}
