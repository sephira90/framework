<?php

declare(strict_types=1);

namespace Framework\Foundation\Bootstrap\Provider;

use Framework\Config\Config;
use Framework\Foundation\Bootstrap\BootstrapBuilder;
use Framework\Foundation\Bootstrap\ContainerAccessor;
use Framework\Foundation\Bootstrap\ServiceProviderInterface;
use Framework\Http\ErrorResponseFactory;
use Framework\Http\Middleware\ErrorHandlingMiddleware;
use Framework\Http\RequestFactory;
use Framework\Http\ResponseEmitter;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Override;

/**
 * Регистрирует core framework services, которые не зависят от app routes.
 */
final class CoreServicesProvider implements ServiceProviderInterface
{
    #[Override]
    public function register(BootstrapBuilder $builder): void
    {
        $config = $builder->config();
        $container = $builder->containerBuilder();

        $container->singleton(Config::class, $config);
        $container->singleton(Psr17Factory::class, static fn (): Psr17Factory => new Psr17Factory());
        $container->alias(ResponseFactoryInterface::class, Psr17Factory::class);
        $container->alias(StreamFactoryInterface::class, Psr17Factory::class);
        $container->alias(ServerRequestFactoryInterface::class, Psr17Factory::class);
        $container->alias(UriFactoryInterface::class, Psr17Factory::class);
        $container->alias(UploadedFileFactoryInterface::class, Psr17Factory::class);
        $container->singleton(
            RequestFactory::class,
            static function (ContainerInterface $container): RequestFactory {
                $factory = ContainerAccessor::get($container, Psr17Factory::class);

                return new RequestFactory(
                    new ServerRequestCreator($factory, $factory, $factory, $factory)
                );
            }
        );
        $container->singleton(ResponseEmitter::class, static fn (): ResponseEmitter => new ResponseEmitter());
        $container->singleton(
            ErrorResponseFactory::class,
            static function (ContainerInterface $container): ErrorResponseFactory {
                $config = ContainerAccessor::get($container, Config::class);

                return new ErrorResponseFactory(
                    ContainerAccessor::get($container, ResponseFactoryInterface::class),
                    ContainerAccessor::get($container, StreamFactoryInterface::class),
                    (bool) $config->get('app.debug', false)
                );
            }
        );
        $container->singleton(
            ErrorHandlingMiddleware::class,
            static function (ContainerInterface $container): ErrorHandlingMiddleware {
                return new ErrorHandlingMiddleware(
                    ContainerAccessor::get($container, ErrorResponseFactory::class)
                );
            }
        );
    }
}
