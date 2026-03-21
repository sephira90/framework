<?php

declare(strict_types=1);

namespace Framework\Tests\Console;

use Framework\Config\InvalidConfigurationException;
use Framework\Console\CommandCollection;
use Framework\Console\CommandCollector;
use Framework\Tests\Support\Fixtures\GreetingCommand;
use Framework\Tests\Support\FrameworkTestCase;
use stdClass;

/** @psalm-suppress UnusedClass */
final class CommandCollectorTest extends FrameworkTestCase
{
    public function testCommandCollectorRegistersCommandsInDeterministicOrder(): void
    {
        $collector = new CommandCollector(new CommandCollection());
        $collector->command('app:greet', GreetingCommand::class);
        $collector->command('app:again', GreetingCommand::class);

        self::assertSame([
            'app:greet' => GreetingCommand::class,
            'app:again' => GreetingCommand::class,
        ], $collector->collection()->all());
    }

    public function testCommandCollectorRejectsDuplicateCommandNames(): void
    {
        $collector = new CommandCollector(new CommandCollection());
        $collector->command('app:greet', GreetingCommand::class);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('already registered');

        $collector->command('app:greet', GreetingCommand::class);
    }

    public function testCommandCollectorRejectsNonCommandClasses(): void
    {
        $collector = new CommandCollector(new CommandCollection());

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('must reference a class implementing');

        /** @var class-string $invalidCommand */
        $invalidCommand = stdClass::class;
        $collector->command('app:invalid', $invalidCommand);
    }
}
