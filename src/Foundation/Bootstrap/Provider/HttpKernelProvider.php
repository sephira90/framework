<?php

declare(strict_types=1);

namespace Framework\Foundation\Bootstrap\Provider;

use Framework\Foundation\Application;
use Framework\Container\ContainerEntryOwner;
use Framework\Foundation\Bootstrap\BootableProviderInterface;
use Framework\Foundation\Bootstrap\BootstrapBuilder;
use Framework\Foundation\Bootstrap\BootstrapContext;
use Framework\Foundation\Bootstrap\ContainerAccessor;
use Framework\Foundation\Bootstrap\GlobalMiddlewareFactory;
use Framework\Foundation\Bootstrap\GlobalMiddlewareRegistry;
use Framework\Foundation\Bootstrap\ServiceProviderInterface;
use Framework\Foundation\HttpRuntime;
use Framework\Http\ErrorResponseFactory;
use Framework\Http\HandlerResolver;
use Framework\Http\MiddlewareResolver;
use Framework\Http\RequestFactory;
use Framework\Http\ResponseEmitter;
use Framework\Http\RouteDispatcher;
use Framework\Routing\Router;
use Override;
use Psr\Container\ContainerInterface;

/**
 * Регистрирует и завершает HTTP runtime graph framework.
 */
final readonly class HttpKernelProvider implements ServiceProviderInterface, BootableProviderInterface
{
    public function __construct(
        private GlobalMiddlewareFactory $globalMiddlewareFactory = new GlobalMiddlewareFactory(),
    ) {
    }

    #[Override]
    public function register(BootstrapBuilder $builder): void
    {
        $container = $builder->containerBuilder();

        $container->singleton(
            GlobalMiddlewareRegistry::class,
            new GlobalMiddlewareRegistry(),
            ContainerEntryOwner::Framework,
            self::class
        );
        $container->singleton(
            HandlerResolver::class,
            static function (ContainerInterface $container): HandlerResolver {
                return new HandlerResolver($container);
            },
            ContainerEntryOwner::Framework,
            self::class
        );
        $container->singleton(
            MiddlewareResolver::class,
            static function (ContainerInterface $container): MiddlewareResolver {
                return new MiddlewareResolver($container);
            },
            ContainerEntryOwner::Framework,
            self::class
        );
        $container->singleton(
            RouteDispatcher::class,
            static function (ContainerInterface $container): RouteDispatcher {
                return new RouteDispatcher(
                    ContainerAccessor::get($container, Router::class),
                    ContainerAccessor::get($container, HandlerResolver::class),
                    ContainerAccessor::get($container, MiddlewareResolver::class),
                    ContainerAccessor::get($container, ErrorResponseFactory::class)
                );
            },
            ContainerEntryOwner::Framework,
            self::class
        );
        $container->singleton(
            Application::class,
            static function (ContainerInterface $container): Application {
                $middlewareRegistry = ContainerAccessor::get($container, GlobalMiddlewareRegistry::class);

                return new Application(
                    ContainerAccessor::get($container, RouteDispatcher::class),
                    ContainerAccessor::get($container, MiddlewareResolver::class),
                    $middlewareRegistry->middleware()
                );
            },
            ContainerEntryOwner::Framework,
            self::class
        );
        $container->singleton(
            HttpRuntime::class,
            static function (ContainerInterface $container): HttpRuntime {
                return new HttpRuntime(
                    ContainerAccessor::get($container, Application::class),
                    ContainerAccessor::get($container, RequestFactory::class),
                    ContainerAccessor::get($container, ResponseEmitter::class)
                );
            },
            ContainerEntryOwner::Framework,
            self::class
        );
    }

    #[Override]
    public function boot(BootstrapContext $context): void
    {
        $middleware = $this->globalMiddlewareFactory->create($context->config());
        $registry = ContainerAccessor::get($context->container(), GlobalMiddlewareRegistry::class);
        $registry->initialize($middleware);
    }
}
