<?php

declare(strict_types=1);

namespace Framework\Tests\Foundation\Bootstrap;

use Framework\Config\Config;
use Framework\Console\ArgvInputFactory;
use Framework\Console\CommandRegistry;
use Framework\Console\ConsoleApplication;
use Framework\Container\ContainerBuilder;
use Framework\Foundation\Bootstrap\BootstrapBuilder;
use Framework\Foundation\Bootstrap\BootstrapContext;
use Framework\Foundation\Bootstrap\BootstrapStateException;
use Framework\Foundation\Bootstrap\Provider\ConfiguredServicesProvider;
use Framework\Foundation\Bootstrap\Provider\ConsoleCommandsProvider;
use Framework\Foundation\Bootstrap\Provider\ConsoleKernelProvider;
use Framework\Foundation\Bootstrap\Provider\SharedServicesProvider;
use Framework\Foundation\ConsoleRuntime;
use Framework\Tests\Support\Fixtures\GreetingCommand;
use Framework\Tests\Support\FrameworkTestCase;

/** @psalm-suppress UnusedClass */
final class ConsoleProviderIntegrationTest extends FrameworkTestCase
{
    public function testConsoleCommandsProviderRequiresBootBeforeRegistryAccess(): void
    {
        $config = $this->config();
        $builder = new BootstrapBuilder($this->createTempDirectory(), $config, new ContainerBuilder());
        $provider = new ConsoleCommandsProvider();

        $provider->register($builder);

        /** @var CommandRegistry $registry */
        $registry = $builder->containerBuilder()->build()->get(CommandRegistry::class);

        $this->expectException(BootstrapStateException::class);
        $this->expectExceptionMessage('Command registry has not been initialized yet.');

        $registry->commandNames();
    }

    public function testConsoleProviderBuildsContainerManagedRuntimeGraph(): void
    {
        $basePath = $this->createTempDirectory();
        $this->writeCommandsFile($basePath);

        $config = $this->config();
        [$builder, $commands] = $this->registeredProviders($basePath, $config);
        $container = $builder->containerBuilder()->build();
        $commands->boot(new BootstrapContext($basePath, $config, $container));

        $runtime = $container->get(ConsoleRuntime::class);
        /** @var CommandRegistry $registry */
        $registry = $container->get(CommandRegistry::class);

        self::assertInstanceOf(ConsoleRuntime::class, $runtime);
        self::assertSame($runtime, $container->get(ConsoleRuntime::class));
        self::assertSame($runtime->application(), $container->get(ConsoleApplication::class));
        self::assertSame($runtime->inputFactory(), $container->get(ArgvInputFactory::class));
        self::assertSame([
            'config:cache',
            'route:cache',
            'cache:clear',
            'app:greet',
        ], $registry->commandNames());
    }

    /**
     * @return array{
     *     BootstrapBuilder,
     *     ConsoleCommandsProvider
     * }
     */
    private function registeredProviders(string $basePath, Config $config): array
    {
        $builder = new BootstrapBuilder($basePath, $config, new ContainerBuilder());
        $shared = new SharedServicesProvider();
        $configured = new ConfiguredServicesProvider();
        $commands = new ConsoleCommandsProvider();
        $kernel = new ConsoleKernelProvider();

        $shared->register($builder);
        $configured->register($builder);
        $commands->register($builder);
        $kernel->register($builder);

        return [$builder, $commands];
    }

    private function config(): Config
    {
        return new Config([
            'app' => [
                'name' => 'Framework Test',
                'env' => 'testing',
                'debug' => false,
            ],
            'console' => [
                'commands' => 'commands/console.php',
            ],
            'container' => [
                'bindings' => [],
                'singletons' => [],
                'aliases' => [],
            ],
        ]);
    }

    private function writeCommandsFile(string $basePath): void
    {
        $this->writeFile($basePath, 'commands/console.php', <<<'PHP'
<?php

declare(strict_types=1);

use Framework\Console\CommandCollector;
use Framework\Tests\Support\Fixtures\GreetingCommand;

return static function (CommandCollector $commands): void {
    $commands->command('app:greet', GreetingCommand::class);
};
PHP);
    }
}
