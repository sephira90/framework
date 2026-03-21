<?php

declare(strict_types=1);

namespace Framework\Tests\Support\Fixtures;

use Framework\Console\CommandInput;
use Framework\Console\CommandInterface;
use Framework\Console\ConsoleOutput;
use Override;

/** @psalm-suppress PossiblyUnusedMethod */
final class MissingDependencyCommand implements CommandInterface
{
    /** @psalm-suppress PossiblyUnusedMethod */
    public function __construct(
        private string $message,
    ) {
    }

    #[Override]
    public function execute(CommandInput $input, ConsoleOutput $output): int
    {
        unset($input, $output);

        return $this->message === '' ? 1 : 0;
    }
}
