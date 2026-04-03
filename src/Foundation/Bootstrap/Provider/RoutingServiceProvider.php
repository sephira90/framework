<?php

declare(strict_types=1);

namespace Framework\Foundation\Bootstrap\Provider;

use Framework\Container\ContainerEntryOwner;
use Framework\Foundation\Bootstrap\BootableProviderInterface;
use Framework\Foundation\Bootstrap\BootstrapBuilder;
use Framework\Foundation\Bootstrap\BootstrapContext;
use Framework\Foundation\Bootstrap\ContainerAccessor;
use Framework\Foundation\Bootstrap\RouteRegistry;
use Framework\Foundation\Bootstrap\RoutesFileLoader;
use Framework\Foundation\Bootstrap\ServiceProviderInterface;
use Framework\Routing\Router;
use Override;
use Psr\Container\ContainerInterface;

/**
 * Поднимает route boot state и регистрирует Router как container-managed service.
 */
final readonly class RoutingServiceProvider implements ServiceProviderInterface, BootableProviderInterface
{
    public function __construct(
        private RoutesFileLoader $routesLoader = new RoutesFileLoader(),
    ) {
    }

    #[Override]
    public function register(BootstrapBuilder $builder): void
    {
        $container = $builder->containerBuilder();

        $container->singleton(
            RouteRegistry::class,
            new RouteRegistry(),
            ContainerEntryOwner::Framework,
            self::class
        );
        $container->singleton(
            Router::class,
            static function (ContainerInterface $container): Router {
                $registry = ContainerAccessor::get($container, RouteRegistry::class);

                return new Router($registry->routes());
            },
            ContainerEntryOwner::Framework,
            self::class
        );
    }

    #[Override]
    public function boot(BootstrapContext $context): void
    {
        $routes = $this->routesLoader->load($context->basePath(), $context->config());
        $registry = ContainerAccessor::get($context->container(), RouteRegistry::class);
        $registry->initialize($routes);
    }
}
