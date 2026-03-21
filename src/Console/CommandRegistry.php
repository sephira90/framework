<?php

declare(strict_types=1);

namespace Framework\Console;

use Framework\Foundation\Bootstrap\BootstrapStateException;

/**
 * Single-assignment registry зарегистрированных console commands.
 */
final class CommandRegistry
{
    /** @var array<string, class-string<CommandInterface>>|null */
    private ?array $commands = null;

    public function initialize(CommandCollection $collection): void
    {
        if ($this->commands !== null) {
            throw new BootstrapStateException('Command registry has already been initialized.');
        }

        $this->commands = $collection->all();
    }

    /**
     * @return list<string>
     */
    public function commandNames(): array
    {
        if ($this->commands === null) {
            throw new BootstrapStateException('Command registry has not been initialized yet.');
        }

        return array_keys($this->commands);
    }

    /**
     * @return class-string<CommandInterface>|null
     */
    public function commandHandler(string $name): ?string
    {
        if ($this->commands === null) {
            throw new BootstrapStateException('Command registry has not been initialized yet.');
        }

        return $this->commands[$name] ?? null;
    }

    public function isInitialized(): bool
    {
        return $this->commands !== null;
    }
}
