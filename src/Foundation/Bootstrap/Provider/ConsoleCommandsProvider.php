<?php

declare(strict_types=1);

namespace Framework\Foundation\Bootstrap\Provider;

use Framework\Console\CommandRegistry;
use Framework\Foundation\Bootstrap\BootableProviderInterface;
use Framework\Foundation\Bootstrap\BootstrapBuilder;
use Framework\Foundation\Bootstrap\BootstrapContext;
use Framework\Foundation\Bootstrap\CommandsFileLoader;
use Framework\Foundation\Bootstrap\ContainerAccessor;
use Framework\Foundation\Bootstrap\ServiceProviderInterface;
use Override;

/**
 * Поднимает command boot state для console runtime.
 */
final readonly class ConsoleCommandsProvider implements ServiceProviderInterface, BootableProviderInterface
{
    public function __construct(
        private CommandsFileLoader $commandsLoader = new CommandsFileLoader(),
    ) {
    }

    #[Override]
    public function register(BootstrapBuilder $builder): void
    {
        $builder->containerBuilder()->singleton(CommandRegistry::class, new CommandRegistry());
    }

    #[Override]
    public function boot(BootstrapContext $context): void
    {
        $commands = $this->commandsLoader->load($context->basePath(), $context->config());
        $registry = ContainerAccessor::get($context->container(), CommandRegistry::class);
        $registry->initialize($commands);
    }
}
