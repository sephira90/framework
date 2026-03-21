<?php

declare(strict_types=1);

namespace Framework\Tests\Foundation\Bootstrap;

use Framework\Console\CommandCollection;
use Framework\Console\CommandRegistry;
use Framework\Foundation\Bootstrap\BootstrapStateException;
use Framework\Tests\Support\Fixtures\GreetingCommand;
use Framework\Tests\Support\FrameworkTestCase;

/** @psalm-suppress UnusedClass */
final class CommandRegistryTest extends FrameworkTestCase
{
    public function testCommandRegistryThrowsBeforeInitialization(): void
    {
        $registry = new CommandRegistry();

        self::assertFalse($registry->isInitialized());

        $this->expectException(BootstrapStateException::class);
        $this->expectExceptionMessage('Command registry has not been initialized yet.');

        $registry->commandNames();
    }

    public function testCommandRegistryRejectsRepeatedInitialization(): void
    {
        $collection = new CommandCollection();
        $collection->add('app:greet', GreetingCommand::class);

        $registry = new CommandRegistry();
        $registry->initialize($collection);

        self::assertTrue($registry->isInitialized());

        $this->expectException(BootstrapStateException::class);
        $this->expectExceptionMessage('Command registry has already been initialized.');

        $registry->initialize($collection);
    }
}
