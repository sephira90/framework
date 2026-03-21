<?php

declare(strict_types=1);

namespace Framework\Console;

/**
 * Явный контракт app command для console kernel.
 */
interface CommandInterface
{
    /**
     * Исполняет команду и возвращает итоговый exit code.
     */
    public function execute(CommandInput $input, ConsoleOutput $output): int;
}
