<?php

declare(strict_types=1);

namespace Framework\Tests\Foundation;

use Framework\Config\InvalidConfigurationException;
use Framework\Console\ConsoleOutput;
use Framework\Foundation\ConsoleApplicationFactory;
use Framework\Foundation\ConsoleRuntime;
use Framework\Tests\Support\Fixtures\ExitCodeCommand;
use Framework\Tests\Support\Fixtures\ExplodingCommand;
use Framework\Tests\Support\Fixtures\GreetingCommand;
use Framework\Tests\Support\Fixtures\MissingDependencyCommand;
use Framework\Tests\Support\FrameworkTestCase;

/** @psalm-suppress UnusedClass */
final class ConsoleApplicationFactoryTest extends FrameworkTestCase
{
    public function testConsoleApplicationFactoryBuildsRuntimeForConfiguredCommands(): void
    {
        $runtime = $this->createRuntime(
            <<<'PHP'
<?php

declare(strict_types=1);

use Framework\Console\CommandCollector;
use Framework\Tests\Support\Fixtures\GreetingCommand;

return static function (CommandCollector $commands): void {
    $commands->command('app:greet', GreetingCommand::class);
};
PHP,
            singletons: [
                GreetingCommand::class => GreetingCommand::class,
            ]
        );

        [$output, $stdout, $stderr] = $this->createOutput();
        $input = $runtime->inputFactory()->fromArgv(['bin/console', 'app:greet']);
        $code = $runtime->application()->run($input, $output);

        self::assertSame(0, $code);
        self::assertSame('hello' . PHP_EOL, $this->readStream($stdout));
        self::assertSame('', $this->readStream($stderr));
    }

    public function testConsoleApplicationReturnsCommandExitCodeWithoutExtraOutput(): void
    {
        $runtime = $this->createRuntime(
            <<<'PHP'
<?php

declare(strict_types=1);

use Framework\Console\CommandCollector;
use Framework\Tests\Support\Fixtures\ExitCodeCommand;

return static function (CommandCollector $commands): void {
    $commands->command('app:exit', ExitCodeCommand::class);
};
PHP,
            singletons: [
                ExitCodeCommand::class => ExitCodeCommand::class,
            ]
        );

        [$output, $stdout, $stderr] = $this->createOutput();
        $input = $runtime->inputFactory()->fromArgv(['bin/console', 'app:exit']);
        $code = $runtime->application()->run($input, $output);

        self::assertSame(7, $code);
        self::assertSame('', $this->readStream($stdout));
        self::assertSame('', $this->readStream($stderr));
    }

    public function testConsoleApplicationRendersUsageWhenCommandNameIsMissing(): void
    {
        $runtime = $this->createRuntime(
            <<<'PHP'
<?php

declare(strict_types=1);

use Framework\Console\CommandCollector;
use Framework\Tests\Support\Fixtures\GreetingCommand;

return static function (CommandCollector $commands): void {
    $commands->command('app:greet', GreetingCommand::class);
};
PHP,
            singletons: [
                GreetingCommand::class => GreetingCommand::class,
            ]
        );

        [$output, $stdout, $stderr] = $this->createOutput();
        $input = $runtime->inputFactory()->fromArgv(['bin/console']);
        $code = $runtime->application()->run($input, $output);
        $error = $this->readStream($stderr);

        self::assertSame(1, $code);
        self::assertSame('', $this->readStream($stdout));
        self::assertStringContainsString('Command name is required.', $error);
        self::assertStringContainsString('Usage: console <command> [arguments] [--options]', $error);
        self::assertStringContainsString('app:greet', $error);
    }

    public function testConsoleApplicationRendersUnknownCommand(): void
    {
        $runtime = $this->createRuntime(
            <<<'PHP'
<?php

declare(strict_types=1);

use Framework\Console\CommandCollector;
use Framework\Tests\Support\Fixtures\GreetingCommand;

return static function (CommandCollector $commands): void {
    $commands->command('app:greet', GreetingCommand::class);
};
PHP,
            singletons: [
                GreetingCommand::class => GreetingCommand::class,
            ]
        );

        [$output, $stdout, $stderr] = $this->createOutput();
        $input = $runtime->inputFactory()->fromArgv(['bin/console', 'app:missing']);
        $code = $runtime->application()->run($input, $output);
        $error = $this->readStream($stderr);

        self::assertSame(1, $code);
        self::assertSame('', $this->readStream($stdout));
        self::assertStringContainsString('Command [app:missing] is not registered.', $error);
        self::assertStringContainsString('app:greet', $error);
    }

    public function testConsoleApplicationHidesUnexpectedThrowableOutsideDebugMode(): void
    {
        $runtime = $this->createRuntime(
            <<<'PHP'
<?php

declare(strict_types=1);

use Framework\Console\CommandCollector;
use Framework\Tests\Support\Fixtures\ExplodingCommand;

return static function (CommandCollector $commands): void {
    $commands->command('app:explode', ExplodingCommand::class);
};
PHP,
            debug: false,
            singletons: [
                ExplodingCommand::class => ExplodingCommand::class,
            ]
        );

        [$output, $stdout, $stderr] = $this->createOutput();
        $input = $runtime->inputFactory()->fromArgv(['bin/console', 'app:explode']);
        $code = $runtime->application()->run($input, $output);

        self::assertSame(1, $code);
        self::assertSame('', $this->readStream($stdout));
        self::assertStringContainsString('Command failed.', $this->readStream($stderr));
    }

    public function testConsoleApplicationExposesUnexpectedThrowableInDebugMode(): void
    {
        $runtime = $this->createRuntime(
            <<<'PHP'
<?php

declare(strict_types=1);

use Framework\Console\CommandCollector;
use Framework\Tests\Support\Fixtures\ExplodingCommand;

return static function (CommandCollector $commands): void {
    $commands->command('app:explode', ExplodingCommand::class);
};
PHP,
            debug: true,
            singletons: [
                ExplodingCommand::class => ExplodingCommand::class,
            ]
        );

        [$output, $stdout, $stderr] = $this->createOutput();
        $input = $runtime->inputFactory()->fromArgv(['bin/console', 'app:explode']);
        $code = $runtime->application()->run($input, $output);

        self::assertSame(1, $code);
        self::assertSame('', $this->readStream($stdout));
        self::assertStringContainsString('RuntimeException: boom', $this->readStream($stderr));
    }

    public function testConsoleApplicationReportsCommandResolutionFailures(): void
    {
        $runtime = $this->createRuntime(
            <<<'PHP'
<?php

declare(strict_types=1);

use Framework\Console\CommandCollector;
use Framework\Tests\Support\Fixtures\MissingDependencyCommand;

return static function (CommandCollector $commands): void {
    $commands->command('app:broken', MissingDependencyCommand::class);
};
PHP,
            debug: true,
            singletons: [
                MissingDependencyCommand::class => MissingDependencyCommand::class,
            ]
        );

        [$output, $stdout, $stderr] = $this->createOutput();
        $input = $runtime->inputFactory()->fromArgv(['bin/console', 'app:broken']);
        $code = $runtime->application()->run($input, $output);

        self::assertSame(1, $code);
        self::assertSame('', $this->readStream($stdout));
        self::assertStringContainsString('needs an explicit factory', $this->readStream($stderr));
    }

    public function testConsoleApplicationFactoryFailsWhenCommandsFileIsMissing(): void
    {
        $basePath = $this->createTempDirectory();

        $this->writeConfigFiles($basePath, false, 'commands/missing.php');

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('was not found');

        ConsoleApplicationFactory::createRuntime($basePath);
    }

    public function testConsoleApplicationFactoryFailsWhenCommandsFileDoesNotReturnCallableRegistrar(): void
    {
        $basePath = $this->createTempDirectory();

        $this->writeConfigFiles($basePath);
        $this->writeFile($basePath, 'commands/console.php', <<<'PHP'
<?php

declare(strict_types=1);

return [];
PHP);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('must return a callable registrar');

        ConsoleApplicationFactory::createRuntime($basePath);
    }

    /**
     * @param array<class-string, class-string> $singletons
     */
    private function createRuntime(string $commandsSource, bool $debug = false, array $singletons = []): ConsoleRuntime
    {
        $basePath = $this->createTempDirectory();

        $this->writeConfigFiles($basePath, $debug, 'commands/console.php', $singletons);
        $this->writeFile($basePath, 'commands/console.php', $commandsSource);

        return ConsoleApplicationFactory::createRuntime($basePath);
    }

    /**
     * @param array<class-string, class-string> $singletons
     */
    private function writeConfigFiles(
        string $basePath,
        bool $debug = false,
        string $commandsPath = 'commands/console.php',
        array $singletons = []
    ): void {
        $this->writeFile(
            $basePath,
            'config/app.php',
            "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export([
                'app' => [
                    'name' => 'Framework Test',
                    'env' => 'testing',
                    'debug' => $debug,
                ],
            ], true) . ";\n"
        );
        $this->writeFile(
            $basePath,
            'config/console.php',
            "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export([
                'console' => [
                    'commands' => $commandsPath,
                ],
            ], true) . ";\n"
        );
        $this->writeFile(
            $basePath,
            'config/container.php',
            "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export([
                'container' => [
                    'bindings' => [],
                    'singletons' => $singletons,
                    'aliases' => [],
                ],
            ], true) . ";\n"
        );
    }

    /**
     * @return array{ConsoleOutput, resource, resource}
     */
    private function createOutput(): array
    {
        $stdout = fopen('php://temp', 'w+b');
        $stderr = fopen('php://temp', 'w+b');

        self::assertIsResource($stdout);
        self::assertIsResource($stderr);

        return [new ConsoleOutput($stdout, $stderr), $stdout, $stderr];
    }

    /**
     * @param resource $stream
     */
    private function readStream(mixed $stream): string
    {
        rewind($stream);

        $contents = stream_get_contents($stream);

        return is_string($contents) ? $contents : '';
    }
}
