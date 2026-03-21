<?php

declare(strict_types=1);

namespace Framework\Tests\Support\Fixtures;

use Framework\Console\CommandInput;
use Framework\Console\CommandInterface;
use Framework\Console\ConsoleOutput;
use Override;

final class GreetingCommand implements CommandInterface
{
    #[Override]
    public function execute(CommandInput $input, ConsoleOutput $output): int
    {
        unset($input);

        $output->writeln('hello');

        return 0;
    }
}
