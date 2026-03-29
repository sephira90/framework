<?php

declare(strict_types=1);

namespace Framework\Foundation\Bootstrap;

/**
 * Immutable validated snapshot of user-defined container configuration.
 */
final readonly class ConfiguredContainerConfig
{
    /**
     * @param array<string, callable|string|object> $bindings
     * @param array<string, callable|string|object> $singletons
     * @param array<string, string> $aliases
     */
    public function __construct(
        private array $bindings,
        private array $singletons,
        private array $aliases,
    ) {
    }

    /**
     * @param array{
     *     bindings: array<string, callable|string|object>,
     *     singletons: array<string, callable|string|object>,
     *     aliases: array<string, string>
     * } $data
     */
    public static function __set_state(array $data): self
    {
        return new self(
            $data['bindings'],
            $data['singletons'],
            $data['aliases']
        );
    }

    /**
     * @return array<string, callable|string|object>
     */
    public function bindings(): array
    {
        return $this->bindings;
    }

    /**
     * @return array<string, callable|string|object>
     */
    public function singletons(): array
    {
        return $this->singletons;
    }

    /**
     * @return array<string, string>
     */
    public function aliases(): array
    {
        return $this->aliases;
    }
}
