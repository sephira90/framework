<?php

declare(strict_types=1);

namespace Framework\Foundation\Bootstrap\Provider;

use Framework\Config\Config;
use Framework\Console\ArgvInputFactory;
use Framework\Console\CommandRegistry;
use Framework\Console\CommandResolver;
use Framework\Console\ConsoleApplication;
use Framework\Console\ConsoleErrorRenderer;
use Framework\Container\ContainerEntryOwner;
use Framework\Foundation\Bootstrap\BootstrapBuilder;
use Framework\Foundation\Bootstrap\ContainerAccessor;
use Framework\Foundation\Bootstrap\ServiceProviderInterface;
use Framework\Foundation\ConsoleRuntime;
use Override;
use Psr\Container\ContainerInterface;

/**
 * Регистрирует CLI-specific runtime graph framework.
 */
final class ConsoleKernelProvider implements ServiceProviderInterface
{
    #[Override]
    public function register(BootstrapBuilder $builder): void
    {
        $container = $builder->containerBuilder();

        $container->singleton(
            ArgvInputFactory::class,
            static fn (): ArgvInputFactory => new ArgvInputFactory(),
            ContainerEntryOwner::Framework,
            self::class
        );
        $container->singleton(
            CommandResolver::class,
            static function (ContainerInterface $container): CommandResolver {
                return new CommandResolver($container);
            },
            ContainerEntryOwner::Framework,
            self::class
        );
        $container->singleton(
            ConsoleErrorRenderer::class,
            static function (ContainerInterface $container): ConsoleErrorRenderer {
                $config = ContainerAccessor::get($container, Config::class);

                return new ConsoleErrorRenderer((bool) $config->get('app.debug', false));
            },
            ContainerEntryOwner::Framework,
            self::class
        );
        $container->singleton(
            ConsoleApplication::class,
            static function (ContainerInterface $container): ConsoleApplication {
                return new ConsoleApplication(
                    ContainerAccessor::get($container, CommandRegistry::class),
                    ContainerAccessor::get($container, CommandResolver::class),
                    ContainerAccessor::get($container, ConsoleErrorRenderer::class)
                );
            },
            ContainerEntryOwner::Framework,
            self::class
        );
        $container->singleton(
            ConsoleRuntime::class,
            static function (ContainerInterface $container): ConsoleRuntime {
                return new ConsoleRuntime(
                    ContainerAccessor::get($container, ConsoleApplication::class),
                    ContainerAccessor::get($container, ArgvInputFactory::class)
                );
            },
            ContainerEntryOwner::Framework,
            self::class
        );
    }
}
