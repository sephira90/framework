<?php

declare(strict_types=1);

namespace Framework\Tests\Foundation\Bootstrap;

use Framework\Config\Config;
use Framework\Config\InvalidConfigurationException;
use Framework\Foundation\Bootstrap\CommandsFileLoader;
use Framework\Tests\Support\Fixtures\GreetingCommand;
use Framework\Tests\Support\FrameworkTestCase;

/** @psalm-suppress UnusedClass */
final class CommandsFileLoaderTest extends FrameworkTestCase
{
    public function testCommandsFileLoaderLoadsConfiguredProjectFile(): void
    {
        $basePath = $this->createTempDirectory();
        $this->writeFile($basePath, 'commands/console.php', <<<'PHP'
<?php

declare(strict_types=1);

use Framework\Console\CommandCollector;
use Framework\Tests\Support\Fixtures\GreetingCommand;

return static function (CommandCollector $commands): void {
    $commands->command('app:greet', GreetingCommand::class);
};
PHP);

        $collection = (new CommandsFileLoader())->load($basePath, new Config([
            'console' => [
                'commands' => 'commands/console.php',
            ],
        ]));

        self::assertSame(['app:greet' => GreetingCommand::class], $collection->all());
    }

    public function testCommandsFileLoaderRejectsMissingFile(): void
    {
        $basePath = $this->createTempDirectory();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('was not found');

        (new CommandsFileLoader())->load($basePath, new Config([
            'console' => [
                'commands' => 'commands/missing.php',
            ],
        ]));
    }

    public function testCommandsFileLoaderRejectsNonCallableRegistrar(): void
    {
        $basePath = $this->createTempDirectory();
        $this->writeFile($basePath, 'commands/console.php', <<<'PHP'
<?php

declare(strict_types=1);

return [];
PHP);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('must return a callable registrar');

        (new CommandsFileLoader())->load($basePath, new Config([
            'console' => [
                'commands' => 'commands/console.php',
            ],
        ]));
    }

    public function testCommandsFileLoaderRejectsAbsolutePathEscape(): void
    {
        $basePath = $this->createTempDirectory();
        $outsidePath = $this->createTempDirectory();
        $absolutePath = $outsidePath . DIRECTORY_SEPARATOR . 'console.php';
        $this->writeFile($outsidePath, 'console.php', <<<'PHP'
<?php

declare(strict_types=1);

use Framework\Console\CommandCollector;
use Framework\Tests\Support\Fixtures\GreetingCommand;

return static function (CommandCollector $commands): void {
    $commands->command('app:greet', GreetingCommand::class);
};
PHP);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('relative project file path');

        (new CommandsFileLoader())->load($basePath, new Config([
            'console' => [
                'commands' => $absolutePath,
            ],
        ]));
    }
}
