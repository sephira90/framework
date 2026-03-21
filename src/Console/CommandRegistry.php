<?php

declare(strict_types=1);

namespace Framework\Console;

use Framework\Foundation\Bootstrap\SingleAssignmentHolder;

/**
 * Single-assignment registry зарегистрированных console commands.
 */
final class CommandRegistry
{
    /** @var SingleAssignmentHolder<array<string, class-string<CommandInterface>>> */
    private SingleAssignmentHolder $commands;

    public function __construct()
    {
        /** @var SingleAssignmentHolder<array<string, class-string<CommandInterface>>> $commands */
        $commands = new SingleAssignmentHolder('Command registry');

        $this->commands = $commands;
    }

    public function initialize(CommandCollection $collection): void
    {
        $this->commands->initialize($collection->all());
    }

    /**
     * @return list<string>
     */
    public function commandNames(): array
    {
        $commands = $this->commands->get();

        return array_keys($commands);
    }

    /**
     * @return class-string<CommandInterface>|null
     */
    public function commandHandler(string $name): ?string
    {
        $commands = $this->commands->get();

        return $commands[$name] ?? null;
    }

    public function isInitialized(): bool
    {
        return $this->commands->isInitialized();
    }
}
