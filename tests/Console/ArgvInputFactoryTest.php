<?php

declare(strict_types=1);

namespace Framework\Tests\Console;

use Framework\Console\ArgvInputFactory;
use Framework\Tests\Support\FrameworkTestCase;

/** @psalm-suppress UnusedClass */
final class ArgvInputFactoryTest extends FrameworkTestCase
{
    public function testArgvInputFactoryReturnsMissingCommandWhenNoTokensRemain(): void
    {
        $input = (new ArgvInputFactory())->fromArgv(['bin/console']);

        self::assertNull($input->commandName());
        self::assertSame([], $input->arguments());
        self::assertSame([], $input->options());
        self::assertSame([], $input->rawTokens());
    }

    public function testArgvInputFactoryParsesPositionalArgumentsAndLongOptions(): void
    {
        $input = (new ArgvInputFactory())->fromArgv([
            'bin/console',
            'app:greet',
            'first',
            '--flag',
            '--name=framework',
            'second',
        ]);

        self::assertSame('app:greet', $input->commandName());
        self::assertSame(['first', 'second'], $input->arguments());
        self::assertSame('first', $input->argument(0));
        self::assertSame(['flag' => true, 'name' => 'framework'], $input->options());
        self::assertSame(
            ['app:greet', 'first', '--flag', '--name=framework', 'second'],
            $input->rawTokens()
        );
    }

    public function testArgvInputFactoryStopsParsingOptionsAfterDoubleDash(): void
    {
        $input = (new ArgvInputFactory())->fromArgv([
            'bin/console',
            'app:greet',
            '--flag',
            '--',
            '--name=literal',
            'tail',
        ]);

        self::assertSame('app:greet', $input->commandName());
        self::assertSame(['--name=literal', 'tail'], $input->arguments());
        self::assertSame(['flag' => true], $input->options());
    }

    public function testArgvInputFactoryUsesLastRepeatedOptionValue(): void
    {
        $input = (new ArgvInputFactory())->fromArgv([
            'bin/console',
            'app:greet',
            '--name=first',
            '--name=second',
        ]);

        self::assertSame('second', $input->option('name'));
    }
}
