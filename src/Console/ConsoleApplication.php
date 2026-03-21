<?php

declare(strict_types=1);

namespace Framework\Console;

use Throwable;

/**
 * Top-level CLI kernel.
 */
final readonly class ConsoleApplication
{
    public function __construct(
        private CommandRegistry $commands,
        private CommandResolver $resolver,
        private ConsoleErrorRenderer $errorRenderer,
    ) {
    }

    public function run(CommandInput $input, ConsoleOutput $output): int
    {
        $commandName = $input->commandName();

        if ($commandName === null) {
            $this->errorRenderer->renderMissingCommand($this->commands->commandNames(), $output);
            return 1;
        }

        $commandHandler = $this->commands->commandHandler($commandName);

        if ($commandHandler === null) {
            $this->errorRenderer->renderUnknownCommand($commandName, $this->commands->commandNames(), $output);
            return 1;
        }

        try {
            return $this->resolver->resolve($commandHandler)->execute($input, $output);
        } catch (Throwable $throwable) {
            $this->errorRenderer->renderThrowable($throwable, $output);
            return 1;
        }
    }
}
