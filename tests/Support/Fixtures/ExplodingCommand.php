<?php

declare(strict_types=1);

namespace Framework\Tests\Support\Fixtures;

use Framework\Console\CommandInput;
use Framework\Console\CommandInterface;
use Framework\Console\ConsoleOutput;
use Override;
use RuntimeException;

final class ExplodingCommand implements CommandInterface
{
    #[Override]
    public function execute(CommandInput $input, ConsoleOutput $output): int
    {
        unset($input, $output);

        throw new RuntimeException('boom');
    }
}
