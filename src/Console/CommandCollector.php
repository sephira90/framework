<?php

declare(strict_types=1);

namespace Framework\Console;

/**
 * Registration API для commands file.
 */
final readonly class CommandCollector
{
    public function __construct(
        private CommandCollection $commands,
    ) {
    }

    public function command(string $name, string $handler): void
    {
        $this->commands->add($name, $handler);
    }

    public function collection(): CommandCollection
    {
        return $this->commands;
    }
}
