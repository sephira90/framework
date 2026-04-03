<?php

declare(strict_types=1);

namespace Framework\Foundation\Bootstrap\Provider;

use Framework\Console\CommandCollection;
use Framework\Console\CommandInterface;
use Framework\Console\CommandRegistry;
use Framework\Console\Internal\CacheClearCommand;
use Framework\Console\Internal\ConfigCacheCommand;
use Framework\Console\Internal\ConfigShowCommand;
use Framework\Console\Internal\ContainerDebugCommand;
use Framework\Container\ContainerEntryOwner;
use Framework\Console\Internal\RouteCacheCommand;
use Framework\Console\Internal\RouteListCommand;
use Framework\Foundation\Bootstrap\BootableProviderInterface;
use Framework\Foundation\Bootstrap\BootstrapBuilder;
use Framework\Foundation\Bootstrap\BootstrapContext;
use Framework\Foundation\Bootstrap\CommandsFileLoader;
use Framework\Foundation\Bootstrap\ContainerAccessor;
use Framework\Foundation\Bootstrap\FrameworkCachePaths;
use Framework\Foundation\Bootstrap\ServiceProviderInterface;
use Framework\Config\InvalidConfigurationException;
use Override;

/**
 * Owns CLI command boot state and all framework-managed internal commands.
 *
 * Provider audit rule: shared services keep one owner, so this provider owns
 * only command registration state and framework-owned CLI commands. It must
 * not become a generic shared-services bucket.
 *
 * Observability commands live here on purpose: they belong to the console axis,
 * but they must stay read-only and must not turn this provider into a runtime
 * inspection service locator.
 */
final readonly class ConsoleCommandsProvider implements ServiceProviderInterface, BootableProviderInterface
{
    /** @var list<string> */
    private const RESERVED_PREFIXES = ['config:', 'route:', 'cache:', 'container:'];

    /** @var array<string, class-string<CommandInterface>> */
    private const INTERNAL_COMMANDS = [
        'config:cache' => ConfigCacheCommand::class,
        'config:show' => ConfigShowCommand::class,
        'route:cache' => RouteCacheCommand::class,
        'route:list' => RouteListCommand::class,
        'cache:clear' => CacheClearCommand::class,
        'container:debug' => ContainerDebugCommand::class,
    ];

    public function __construct(
        private CommandsFileLoader $commandsLoader = new CommandsFileLoader(),
    ) {
    }

    #[Override]
    public function register(BootstrapBuilder $builder): void
    {
        $container = $builder->containerBuilder();

        $container->singleton(
            CommandRegistry::class,
            new CommandRegistry(),
            ContainerEntryOwner::Framework,
            self::class
        );
        $container->singleton(
            ConfigCacheCommand::class,
            new ConfigCacheCommand($builder->basePath()),
            ContainerEntryOwner::Framework,
            self::class
        );
        $container->singleton(
            RouteCacheCommand::class,
            new RouteCacheCommand($builder->basePath()),
            ContainerEntryOwner::Framework,
            self::class
        );
        $container->singleton(
            ConfigShowCommand::class,
            new ConfigShowCommand($builder->basePath()),
            ContainerEntryOwner::Framework,
            self::class
        );
        $container->singleton(
            RouteListCommand::class,
            new RouteListCommand($builder->basePath()),
            ContainerEntryOwner::Framework,
            self::class
        );
        $container->singleton(
            CacheClearCommand::class,
            new CacheClearCommand(new FrameworkCachePaths($builder->basePath())),
            ContainerEntryOwner::Framework,
            self::class
        );
        $container->singleton(
            ContainerDebugCommand::class,
            new ContainerDebugCommand($builder->basePath()),
            ContainerEntryOwner::Framework,
            self::class
        );
    }

    #[Override]
    public function boot(BootstrapContext $context): void
    {
        $commands = $this->commandsLoader->load($context->basePath(), $context->config());
        $registry = ContainerAccessor::get($context->container(), CommandRegistry::class);
        $registry->initialize($this->mergeFrameworkAndApplicationCommands($commands));
    }

    private function mergeFrameworkAndApplicationCommands(CommandCollection $applicationCommands): CommandCollection
    {
        $commands = new CommandCollection();

        foreach (self::INTERNAL_COMMANDS as $name => $handler) {
            $commands->add($name, $handler);
        }

        foreach ($applicationCommands->all() as $name => $handler) {
            $this->assertNotReservedPrefix($name);
            $commands->add($name, $handler);
        }

        return $commands;
    }

    private function assertNotReservedPrefix(string $commandName): void
    {
        foreach (self::RESERVED_PREFIXES as $prefix) {
            if (str_starts_with($commandName, $prefix)) {
                throw new InvalidConfigurationException(sprintf(
                    'Command [%s] uses reserved framework prefix [%s].',
                    $commandName,
                    $prefix
                ));
            }
        }
    }
}
