<?php

declare(strict_types=1);

namespace Framework\Tests\Foundation;

use App\Console\Command\AboutCommand;
use App\Support\AppServiceFactory;
use Framework\Config\InvalidConfigurationException;
use Framework\Console\CommandCollector;
use Framework\Console\ConsoleOutput;
use Framework\Foundation\ApplicationFactory;
use Framework\Foundation\Bootstrap\FrameworkCachePaths;
use Framework\Foundation\ConsoleApplicationFactory;
use Framework\Tests\Support\Fixtures\GreetingCommand;
use Framework\Tests\Support\Fixtures\HelloHandler;
use Framework\Tests\Support\FrameworkTestCase;
use Nyholm\Psr7\Factory\Psr17Factory;

/** @psalm-suppress UnusedClass */
final class CacheCommandsIntegrationTest extends FrameworkTestCase
{
    public function testConfigCacheCommandBuildsSnapshotUsedByLaterRuntime(): void
    {
        $basePath = $this->createTempDirectory();
        $this->writeProjectConfig(
            $basePath,
            singletons: [
                AboutCommand::class => [AppServiceFactory::class, 'makeAboutCommand'],
            ]
        );
        $this->writeCommandsFile($basePath, <<<'PHP'
<?php

declare(strict_types=1);

use App\Console\Command\AboutCommand;
use Framework\Console\CommandCollector;

return static function (CommandCollector $commands): void {
    $commands->command('app:about', AboutCommand::class);
};
PHP);

        [$cacheCode, $cacheStdout, $cacheStderr] = $this->runConsole($basePath, ['bin/console', 'config:cache']);

        self::assertSame(0, $cacheCode);
        self::assertSame('', $cacheStderr);
        self::assertStringContainsString('config.php', $cacheStdout);
        self::assertFileExists((new FrameworkCachePaths($basePath))->configFile());

        $this->writeProjectConfig(
            $basePath,
            appName: 'Changed Framework',
            singletons: [
                AboutCommand::class => [AppServiceFactory::class, 'makeAboutCommand'],
            ]
        );

        [$aboutCode, $aboutStdout, $aboutStderr] = $this->runConsole($basePath, ['bin/console', 'app:about']);

        self::assertSame(0, $aboutCode);
        self::assertSame('', $aboutStderr);
        self::assertStringContainsString('Framework Test console kernel is running in [testing].', $aboutStdout);
        self::assertStringNotContainsString('Changed Framework', $aboutStdout);
    }

    public function testConfigCacheCommandRejectsClosureBasedContainerDefinitions(): void
    {
        $basePath = $this->createTempDirectory();
        $this->writeProjectConfig($basePath, debug: true);
        $this->writeCommandsFile($basePath, $this->emptyCommandsSource());
        $this->writeFile($basePath, 'config/container.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    'container' => [
        'bindings' => [],
        'singletons' => [
            'broken.service' => static fn (): stdClass => new stdClass(),
        ],
        'aliases' => [],
    ],
];
PHP);

        [$code, $stdout, $stderr] = $this->runConsole($basePath, ['bin/console', 'config:cache']);

        self::assertSame(1, $code);
        self::assertSame('', $stdout);
        self::assertStringContainsString('non-exportable', $stderr);
    }

    public function testRouteCacheCommandBuildsCacheUsedByHttpRuntimeWithoutSourceRoutes(): void
    {
        $basePath = $this->createTempDirectory();
        $this->writeProjectConfig(
            $basePath,
            singletons: [
                HelloHandler::class => HelloHandler::class,
            ]
        );
        $this->writeCommandsFile($basePath, $this->emptyCommandsSource());
        $this->writeRoutesFile($basePath, <<<'PHP'
<?php

declare(strict_types=1);

use Framework\Routing\RouteCollector;
use Framework\Tests\Support\Fixtures\HelloHandler;

return static function (RouteCollector $routes): void {
    $routes->get('/hello', HelloHandler::class);
};
PHP);

        [$code, $stdout, $stderr] = $this->runConsole($basePath, ['bin/console', 'route:cache']);

        self::assertSame(0, $code);
        self::assertSame('', $stderr);
        self::assertStringContainsString('routes.php', $stdout);

        unlink($basePath . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php');

        $runtime = ApplicationFactory::createRuntime($basePath);
        $response = $runtime->application()->handle((new Psr17Factory())->createServerRequest('GET', '/hello'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('hello', (string) $response->getBody());
    }

    public function testRouteCacheCommandRejectsClosureHandlers(): void
    {
        $basePath = $this->createTempDirectory();
        $this->writeProjectConfig($basePath, debug: true);
        $this->writeCommandsFile($basePath, $this->emptyCommandsSource());
        $this->writeRoutesFile($basePath, <<<'PHP'
<?php

declare(strict_types=1);

use Framework\Routing\RouteCollector;
use Nyholm\Psr7\Response;

return static function (RouteCollector $routes): void {
    $routes->get('/hello', static fn (): Response => new Response(200, [], 'hello'));
};
PHP);

        [$code, $stdout, $stderr] = $this->runConsole($basePath, ['bin/console', 'route:cache']);

        self::assertSame(1, $code);
        self::assertSame('', $stdout);
        self::assertStringContainsString('cannot be exported because its handler is not a class-string', $stderr);
    }

    public function testCacheClearCommandRemovesFrameworkCaches(): void
    {
        $basePath = $this->createTempDirectory();
        $this->writeProjectConfig($basePath);
        $this->writeCommandsFile($basePath, $this->emptyCommandsSource());

        $cachePaths = new FrameworkCachePaths($basePath);
        $this->writeFile($basePath, 'var/cache/framework/config.php', "<?php\nreturn [];\n");
        $this->writeFile($basePath, 'var/cache/framework/routes.php', "<?php\nreturn [];\n");

        [$code, $stdout, $stderr] = $this->runConsole($basePath, ['bin/console', 'cache:clear']);

        self::assertSame(0, $code);
        self::assertSame('', $stderr);
        self::assertStringContainsString($cachePaths->configFile(), $stdout);
        self::assertStringContainsString($cachePaths->routesFile(), $stdout);
        self::assertFileDoesNotExist($cachePaths->configFile());
        self::assertFileDoesNotExist($cachePaths->routesFile());
    }

    public function testConsoleRuntimeRejectsReservedFrameworkCommandPrefixes(): void
    {
        $basePath = $this->createTempDirectory();
        $this->writeProjectConfig(
            $basePath,
            singletons: [
                GreetingCommand::class => GreetingCommand::class,
            ]
        );
        $this->writeCommandsFile($basePath, <<<'PHP'
<?php

declare(strict_types=1);

use Framework\Console\CommandCollector;
use Framework\Tests\Support\Fixtures\GreetingCommand;

return static function (CommandCollector $commands): void {
    $commands->command('config:custom', GreetingCommand::class);
};
PHP);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('reserved framework prefix');

        ConsoleApplicationFactory::createRuntime($basePath);
    }

    /**
     * @param array<string, string|array{0: string, 1: string}> $singletons
     */
    private function writeProjectConfig(
        string $basePath,
        string $appName = 'Framework Test',
        bool $debug = false,
        array $singletons = [],
    ): void {
        $this->writeFile(
            $basePath,
            'config/app.php',
            $this->phpArraySource([
                'app' => [
                    'name' => $appName,
                    'env' => 'testing',
                    'debug' => $debug,
                ],
            ])
        );
        $this->writeFile(
            $basePath,
            'config/http.php',
            $this->phpArraySource([
                'routes' => 'routes/web.php',
                'middleware' => [],
            ])
        );
        $this->writeFile(
            $basePath,
            'config/console.php',
            $this->phpArraySource([
                'console' => [
                    'commands' => 'commands/console.php',
                ],
            ])
        );
        $this->writeFile(
            $basePath,
            'config/container.php',
            $this->phpArraySource([
                'container' => [
                    'bindings' => [],
                    'singletons' => $singletons,
                    'aliases' => [],
                ],
            ])
        );
    }

    private function writeRoutesFile(string $basePath, string $source): void
    {
        $this->writeFile($basePath, 'routes/web.php', $source);
    }

    private function writeCommandsFile(string $basePath, string $source): void
    {
        $this->writeFile($basePath, 'commands/console.php', $source);
    }

    /**
     * @param list<string> $argv
     * @return array{int, string, string}
     */
    private function runConsole(string $basePath, array $argv): array
    {
        $runtime = ConsoleApplicationFactory::createRuntime($basePath);
        [$output, $stdout, $stderr] = $this->createOutput();
        $code = $runtime->application()->run($runtime->inputFactory()->fromArgv($argv), $output);

        return [$code, $this->readStream($stdout), $this->readStream($stderr)];
    }

    private function emptyCommandsSource(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

use Framework\Console\CommandCollector;

return static function (CommandCollector $commands): void {
};
PHP;
    }

    /**
     * @param array<string, mixed> $array
     */
    private function phpArraySource(array $array): string
    {
        return "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($array, true) . ";\n";
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
