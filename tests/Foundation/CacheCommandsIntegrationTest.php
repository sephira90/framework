<?php

declare(strict_types=1);

namespace Framework\Tests\Foundation;

use App\Console\Command\AboutCommand;
use App\Support\AppServiceFactory;
use Framework\Config\InvalidConfigurationException;
use Framework\Console\ConsoleOutput;
use Framework\Foundation\ConsoleRuntime;
use Framework\Foundation\ApplicationFactory;
use Framework\Foundation\Bootstrap\FrameworkCachePaths;
use Framework\Foundation\ConsoleApplicationFactory;
use Framework\Tests\Support\Fixtures\GreetingCommand;
use Framework\Tests\Support\Fixtures\HelloHandler;
use Framework\Tests\Support\FrameworkTestCase;
use Nyholm\Psr7\Factory\Psr17Factory;

/**
 * @psalm-type DefinitionEntry=array{
 *     entry_type: 'definition',
 *     id: string,
 *     owner: string,
 *     origin: string,
 *     lifecycle: string,
 *     definition_kind: string,
 *     label: string,
 *     requires_container: bool
 * }
 * @psalm-type AliasEntry=array{
 *     entry_type: 'alias',
 *     id: string,
 *     target: string,
 *     owner: string,
 *     origin: string
 * }
 * @psalm-suppress UnusedClass
 */
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

    public function testConfigShowCommandPrintsRuntimeConfigAsJson(): void
    {
        $basePath = $this->createTempDirectory();
        $this->writeProjectConfig(
            $basePath,
            singletons: [
                AboutCommand::class => [AppServiceFactory::class, 'makeAboutCommand'],
            ]
        );
        $this->writeCommandsFile($basePath, $this->emptyCommandsSource());

        [$cacheCode] = $this->runConsole($basePath, ['bin/console', 'config:cache']);

        self::assertSame(0, $cacheCode);

        [$code, $stdout, $stderr] = $this->runConsole($basePath, ['bin/console', 'config:show']);

        self::assertSame(0, $code);
        self::assertSame('', $stderr);

        $decodedValue = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($decodedValue);

        /** @var array{
         *     app: array{name: string},
         *     _framework: array{cache: array{type: string, version: int}}
         * } $decoded
         */
        $decoded = $decodedValue;

        self::assertSame('Framework Test', $decoded['app']['name']);
        self::assertSame('config', $decoded['_framework']['cache']['type']);
        self::assertSame(1, $decoded['_framework']['cache']['version']);
    }

    public function testConfigShowCommandSupportsDottedPathAndSourceBypass(): void
    {
        $basePath = $this->createTempDirectory();
        $this->writeProjectConfig($basePath);
        $this->writeCommandsFile($basePath, $this->emptyCommandsSource());

        [$cacheCode] = $this->runConsole($basePath, ['bin/console', 'config:cache']);

        self::assertSame(0, $cacheCode);

        $this->writeProjectConfig($basePath, appName: 'Changed Framework');

        [$runtimeCode, $runtimeStdout, $runtimeStderr] = $this->runConsole(
            $basePath,
            ['bin/console', 'config:show', 'app.name']
        );
        [$sourceCode, $sourceStdout, $sourceStderr] = $this->runConsole(
            $basePath,
            ['bin/console', 'config:show', 'app.name', '--source']
        );

        self::assertSame(0, $runtimeCode);
        self::assertSame('', $runtimeStderr);
        self::assertSame('"Framework Test"', trim($runtimeStdout));

        self::assertSame(0, $sourceCode);
        self::assertSame('', $sourceStderr);
        self::assertSame('"Changed Framework"', trim($sourceStdout));
    }

    public function testConfigShowCommandReportsMissingPath(): void
    {
        $basePath = $this->createTempDirectory();
        $this->writeProjectConfig($basePath);
        $this->writeCommandsFile($basePath, $this->emptyCommandsSource());

        [$code, $stdout, $stderr] = $this->runConsole($basePath, ['bin/console', 'config:show', 'missing.path']);

        self::assertSame(1, $code);
        self::assertSame('', $stdout);
        self::assertStringContainsString('Config path [missing.path] was not found.', $stderr);
    }

    public function testContainerDebugCommandPrintsRuntimeAndSourceContainerViews(): void
    {
        $basePath = $this->createTempDirectory();
        $this->writeProjectConfig(
            $basePath,
            singletons: [
                AboutCommand::class => [AppServiceFactory::class, 'makeAboutCommand'],
            ],
            aliases: [
                'app.command' => AboutCommand::class,
            ]
        );
        $this->writeCommandsFile($basePath, $this->emptyCommandsSource());

        [$cacheCode] = $this->runConsole($basePath, ['bin/console', 'config:cache']);

        self::assertSame(0, $cacheCode);

        $this->writeProjectConfig(
            $basePath,
            singletons: [
                GreetingCommand::class => GreetingCommand::class,
            ],
            aliases: [
                'app.command' => GreetingCommand::class,
            ]
        );

        [$runtimeCode, $runtimeStdout, $runtimeStderr] = $this->runConsole(
            $basePath,
            ['bin/console', 'container:debug']
        );
        [$sourceCode, $sourceStdout, $sourceStderr] = $this->runConsole(
            $basePath,
            ['bin/console', 'container:debug', '--source']
        );
        [$lookupCode, $lookupStdout, $lookupStderr] = $this->runConsole(
            $basePath,
            ['bin/console', 'container:debug', AboutCommand::class]
        );

        self::assertSame(0, $runtimeCode);
        self::assertSame('', $runtimeStderr);
        self::assertSame(0, $sourceCode);
        self::assertSame('', $sourceStderr);
        self::assertSame(0, $lookupCode);
        self::assertSame('', $lookupStderr);

        $runtimeDecoded = json_decode($runtimeStdout, true, 512, JSON_THROW_ON_ERROR);
        $sourceDecoded = json_decode($sourceStdout, true, 512, JSON_THROW_ON_ERROR);
        $lookupDecoded = json_decode($lookupStdout, true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($runtimeDecoded);
        self::assertIsArray($sourceDecoded);
        self::assertIsArray($lookupDecoded);

        /** @var array{definitions: list<DefinitionEntry>, aliases: list<AliasEntry>} $runtime */
        $runtime = $runtimeDecoded;
        /** @var array{definitions: list<DefinitionEntry>, aliases: list<AliasEntry>} $source */
        $source = $sourceDecoded;
        /** @var DefinitionEntry $lookup */
        $lookup = $lookupDecoded;

        $runtimeAbout = $this->definitionDescriptor($runtime['definitions'], AboutCommand::class);
        $runtimeConsole = $this->definitionDescriptor($runtime['definitions'], ConsoleRuntime::class);
        $sourceGreeting = $this->definitionDescriptor($source['definitions'], GreetingCommand::class);
        $runtimeAlias = $this->aliasDescriptor($runtime['aliases'], 'app.command');
        $sourceAlias = $this->aliasDescriptor($source['aliases'], 'app.command');

        self::assertNotNull($runtimeAbout);
        self::assertSame('application', $runtimeAbout['owner']);
        self::assertSame('container.singletons', $runtimeAbout['origin']);
        self::assertSame('singleton', $runtimeAbout['lifecycle']);
        self::assertSame('callable', $runtimeAbout['definition_kind']);
        self::assertSame(AppServiceFactory::class . '::makeAboutCommand', $runtimeAbout['label']);
        self::assertTrue($runtimeAbout['requires_container']);

        self::assertNotNull($runtimeConsole);
        self::assertSame('framework', $runtimeConsole['owner']);
        self::assertSame('singleton', $runtimeConsole['lifecycle']);

        self::assertNull($this->definitionDescriptor($runtime['definitions'], GreetingCommand::class));

        self::assertNotNull($sourceGreeting);
        self::assertSame('application', $sourceGreeting['owner']);
        self::assertSame('class-string', $sourceGreeting['definition_kind']);
        self::assertSame(GreetingCommand::class, $sourceGreeting['label']);
        self::assertFalse($sourceGreeting['requires_container']);

        self::assertNull($this->definitionDescriptor($source['definitions'], AboutCommand::class));

        self::assertNotNull($runtimeAlias);
        self::assertSame(AboutCommand::class, $runtimeAlias['target']);
        self::assertNotNull($sourceAlias);
        self::assertSame(GreetingCommand::class, $sourceAlias['target']);

        self::assertSame(AboutCommand::class, $lookup['id']);
    }

    public function testContainerDebugCommandReportsMissingEntry(): void
    {
        $basePath = $this->createTempDirectory();
        $this->writeProjectConfig($basePath);
        $this->writeCommandsFile($basePath, $this->emptyCommandsSource());

        [$code, $stdout, $stderr] = $this->runConsole(
            $basePath,
            ['bin/console', 'container:debug', 'missing.entry']
        );

        self::assertSame(1, $code);
        self::assertSame('', $stdout);
        self::assertStringContainsString('Container entry [missing.entry] was not found.', $stderr);
    }

    public function testContainerDebugCommandDoesNotResolveServiceFactories(): void
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
            'exploding.service' => static function (): never {
                throw new RuntimeException('factory should not run');
            },
        ],
        'aliases' => [],
    ],
];
PHP);

        [$code, $stdout, $stderr] = $this->runConsole(
            $basePath,
            ['bin/console', 'container:debug', 'exploding.service']
        );

        self::assertSame(0, $code);
        self::assertSame('', $stderr);

        $decodedValue = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($decodedValue);
        /** @var DefinitionEntry $decoded */
        $decoded = $decodedValue;
        self::assertSame('callable', $decoded['definition_kind']);
        self::assertSame('Closure', $decoded['label']);
        self::assertFalse($decoded['requires_container']);
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

    public function testRouteListCommandSupportsRuntimeAndSourceViews(): void
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
    $routes->get('/cached', HelloHandler::class)->name('cached.home');
    $routes->post('/submit', HelloHandler::class);
};
PHP);

        [$cacheCode] = $this->runConsole($basePath, ['bin/console', 'route:cache']);

        self::assertSame(0, $cacheCode);

        $this->writeRoutesFile($basePath, <<<'PHP'
<?php

declare(strict_types=1);

use Framework\Routing\RouteCollector;
use Framework\Tests\Support\Fixtures\HelloHandler;

return static function (RouteCollector $routes): void {
    $routes->get('/changed', HelloHandler::class)->name('source.home');
    $routes->post('/submit', HelloHandler::class);
    $routes->get('/source-only', HelloHandler::class);
};
PHP);

        [$runtimeCode, $runtimeStdout, $runtimeStderr] = $this->runConsole($basePath, ['bin/console', 'route:list']);
        [$sourceCode, $sourceStdout, $sourceStderr] = $this->runConsole(
            $basePath,
            ['bin/console', 'route:list', '--source']
        );

        self::assertSame(0, $runtimeCode);
        self::assertSame('', $runtimeStderr);
        self::assertStringContainsString("METHODS\tPATH\tNAME\tHANDLER\tMIDDLEWARE", $runtimeStdout);
        self::assertStringContainsString(
            "GET,HEAD\t/cached\tcached.home\t" . HelloHandler::class . "\t0",
            $runtimeStdout
        );
        self::assertStringContainsString("POST\t/submit\t-\t" . HelloHandler::class . "\t0", $runtimeStdout);
        self::assertStringNotContainsString('/changed', $runtimeStdout);

        self::assertSame(0, $sourceCode);
        self::assertSame('', $sourceStderr);
        self::assertStringContainsString(
            "GET,HEAD\t/changed\tsource.home\t" . HelloHandler::class . "\t0",
            $sourceStdout
        );
        self::assertStringContainsString(
            "GET,HEAD\t/source-only\t-\t" . HelloHandler::class . "\t0",
            $sourceStdout
        );
        self::assertStringNotContainsString('/cached', $sourceStdout);
        self::assertLessThan(
            strpos($sourceStdout, '/source-only'),
            strpos($sourceStdout, '/submit')
        );
    }

    public function testCacheClearCommandRemovesFrameworkCaches(): void
    {
        $basePath = $this->createTempDirectory();
        $this->writeProjectConfig($basePath);
        $this->writeCommandsFile($basePath, $this->emptyCommandsSource());

        $cachePaths = new FrameworkCachePaths($basePath);
        $this->writeFile($basePath, 'var/cache/framework/config.php', "<?php\nreturn [];\n");
        $this->writeFile($basePath, 'var/cache/framework/routes.php', "<?php\nreturn [];\n");

        [$code, $stdout, $stderr] = $this->runConsole($basePath, ['bin/console', 'cache:clear'], true);

        self::assertSame(0, $code);
        self::assertSame('', $stderr);
        self::assertStringContainsString($cachePaths->configFile(), $stdout);
        self::assertStringContainsString($cachePaths->routesFile(), $stdout);
        self::assertFileDoesNotExist($cachePaths->configFile());
        self::assertFileDoesNotExist($cachePaths->routesFile());
    }

    public function testCacheClearRecoveryRuntimeRemovesIncompatibleConfigCache(): void
    {
        $basePath = $this->createTempDirectory();
        $this->writeProjectConfig($basePath);
        $this->writeCommandsFile($basePath, $this->emptyCommandsSource());
        $this->writeFile($basePath, 'var/cache/framework/config.php', $this->phpArraySource([
            'app' => [
                'name' => 'Framework Test',
                'env' => 'testing',
                'debug' => false,
            ],
            '_framework' => [
                'cache' => [
                    'type' => 'config',
                    'version' => 999,
                ],
            ],
        ]));

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('unsupported cache version');

        try {
            ConsoleApplicationFactory::createRuntime($basePath);
        } finally {
            [$code, $stdout, $stderr] = $this->runConsole($basePath, ['bin/console', 'cache:clear'], true);

            self::assertSame(0, $code);
            self::assertSame('', $stderr);
            self::assertStringContainsString('config.php', $stdout);
            self::assertFileDoesNotExist((new FrameworkCachePaths($basePath))->configFile());
        }
    }

    public function testRuntimeRejectsIncompatibleRouteCacheSnapshot(): void
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
        $this->writeFile($basePath, 'var/cache/framework/routes.php', $this->phpArraySource([
            'cache' => [
                'type' => 'routes',
                'version' => 999,
            ],
            'index' => [
                'routes' => [],
                'static_routes' => [],
                'dynamic_literal_buckets' => [],
                'dynamic_wildcard_buckets' => [],
                'named_routes' => [],
            ],
        ]));

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('unsupported cache version');

        ApplicationFactory::createRuntime($basePath);
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

    public function testConsoleRuntimeRejectsReservedContainerCommandPrefix(): void
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
    $commands->command('container:custom', GreetingCommand::class);
};
PHP);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('reserved framework prefix');

        ConsoleApplicationFactory::createRuntime($basePath);
    }

    /**
     * @param array<string, string|array{0: string, 1: string}> $singletons
     * @param array<string, string> $aliases
     */
    private function writeProjectConfig(
        string $basePath,
        string $appName = 'Framework Test',
        bool $debug = false,
        array $singletons = [],
        array $aliases = [],
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
                    'aliases' => $aliases,
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
    private function runConsole(string $basePath, array $argv, bool $recovery = false): array
    {
        $runtime = $recovery
            ? ConsoleApplicationFactory::createRecoveryRuntime($basePath)
            : ConsoleApplicationFactory::createRuntime($basePath);
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

    /**
     * @param list<DefinitionEntry> $definitions
     * @return DefinitionEntry|null
     */
    private function definitionDescriptor(array $definitions, string $id): ?array
    {
        foreach ($definitions as $definition) {
            if ($definition['id'] === $id) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * @param list<AliasEntry> $aliases
     * @return AliasEntry|null
     */
    private function aliasDescriptor(array $aliases, string $id): ?array
    {
        foreach ($aliases as $alias) {
            if ($alias['id'] === $id) {
                return $alias;
            }
        }

        return null;
    }
}
