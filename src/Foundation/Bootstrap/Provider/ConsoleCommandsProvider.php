<?php

declare(strict_types=1);

namespace Framework\Foundation\Bootstrap\Provider;

use Framework\Console\CommandCollection;
use Framework\Console\CommandRegistry;
use Framework\Console\Internal\CacheClearCommand;
use Framework\Console\Internal\ConfigCacheCommand;
use Framework\Console\Internal\ConfigShowCommand;
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
 * Поднимает command boot state для console runtime.
 */
final readonly class ConsoleCommandsProvider implements ServiceProviderInterface, BootableProviderInterface
{
    /** @var list<string> */
    private const RESERVED_PREFIXES = ['config:', 'route:', 'cache:'];

    public function __construct(
        private CommandsFileLoader $commandsLoader = new CommandsFileLoader(),
    ) {
    }

    #[Override]
    public function register(BootstrapBuilder $builder): void
    {
        $container = $builder->containerBuilder();

        $container->singleton(CommandRegistry::class, new CommandRegistry());
        $container->singleton(
            ConfigCacheCommand::class,
            new ConfigCacheCommand($builder->basePath())
        );
        $container->singleton(
            ConfigShowCommand::class,
            new ConfigShowCommand($builder->basePath())
        );
        $container->singleton(
            RouteCacheCommand::class,
            new RouteCacheCommand($builder->basePath())
        );
        $container->singleton(
            RouteListCommand::class,
            new RouteListCommand($builder->basePath())
        );
        $container->singleton(
            CacheClearCommand::class,
            new CacheClearCommand(new FrameworkCachePaths($builder->basePath()))
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
        $commands->add('config:cache', ConfigCacheCommand::class);
        $commands->add('config:show', ConfigShowCommand::class);
        $commands->add('route:cache', RouteCacheCommand::class);
        $commands->add('route:list', RouteListCommand::class);
        $commands->add('cache:clear', CacheClearCommand::class);

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
