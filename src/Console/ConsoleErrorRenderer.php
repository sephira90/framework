<?php

declare(strict_types=1);

namespace Framework\Console;

use Throwable;

/**
 * Рендерит boundary-level console errors в stderr.
 */
final readonly class ConsoleErrorRenderer
{
    public function __construct(
        private bool $debug,
    ) {
    }

    /**
     * @param list<string> $commandNames
     */
    public function renderMissingCommand(array $commandNames, ConsoleOutput $output): void
    {
        $output->writeErrorLine('Command name is required.');
        $this->renderUsage($commandNames, $output);
    }

    /**
     * @param list<string> $commandNames
     */
    public function renderUnknownCommand(string $name, array $commandNames, ConsoleOutput $output): void
    {
        $output->writeErrorLine(sprintf('Command [%s] is not registered.', $name));
        $this->renderUsage($commandNames, $output);
    }

    public function renderThrowable(Throwable $throwable, ConsoleOutput $output): void
    {
        if (!$this->debug) {
            $output->writeErrorLine('Command failed.');
            return;
        }

        $output->writeErrorLine(sprintf(
            '%s: %s in %s:%d',
            $throwable::class,
            $throwable->getMessage(),
            $throwable->getFile(),
            $throwable->getLine()
        ));

        $trace = trim($throwable->getTraceAsString());

        if ($trace !== '') {
            $output->writeErrorLine($trace);
        }
    }

    /**
     * @param list<string> $commandNames
     */
    private function renderUsage(array $commandNames, ConsoleOutput $output): void
    {
        $output->writeErrorLine('Usage: console <command> [arguments] [--options]');

        if ($commandNames === []) {
            $output->writeErrorLine('No commands are registered.');
            return;
        }

        $output->writeErrorLine('Available commands:');

        foreach ($commandNames as $name) {
            $output->writeErrorLine('  ' . $name);
        }
    }
}
