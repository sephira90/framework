<?php

declare(strict_types=1);

namespace Framework\Foundation\Bootstrap\Provider;

use Framework\Config\Config;
use Framework\Container\ContainerEntryOwner;
use Framework\Foundation\Bootstrap\BootstrapBuilder;
use Framework\Foundation\Bootstrap\ContainerAccessor;
use Framework\Foundation\Bootstrap\ServiceProviderInterface;
use Framework\Http\ErrorResponseFactory;
use Framework\Http\Middleware\ErrorHandlingMiddleware;
use Framework\Http\RequestFactory;
use Framework\Http\ResponseEmitter;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Override;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;

/**
 * Регистрирует HTTP-specific core services поверх shared bootstrap state.
 */
final class HttpCoreServicesProvider implements ServiceProviderInterface
{
    #[Override]
    public function register(BootstrapBuilder $builder): void
    {
        $container = $builder->containerBuilder();

        $container->singleton(
            Psr17Factory::class,
            static fn (): Psr17Factory => new Psr17Factory(),
            ContainerEntryOwner::Framework,
            self::class
        );
        $container->alias(
            ResponseFactoryInterface::class,
            Psr17Factory::class,
            ContainerEntryOwner::Framework,
            self::class
        );
        $container->alias(
            StreamFactoryInterface::class,
            Psr17Factory::class,
            ContainerEntryOwner::Framework,
            self::class
        );
        $container->alias(
            ServerRequestFactoryInterface::class,
            Psr17Factory::class,
            ContainerEntryOwner::Framework,
            self::class
        );
        $container->alias(
            UriFactoryInterface::class,
            Psr17Factory::class,
            ContainerEntryOwner::Framework,
            self::class
        );
        $container->alias(
            UploadedFileFactoryInterface::class,
            Psr17Factory::class,
            ContainerEntryOwner::Framework,
            self::class
        );
        $container->singleton(
            RequestFactory::class,
            static function (ContainerInterface $container): RequestFactory {
                $factory = ContainerAccessor::get($container, Psr17Factory::class);

                return new RequestFactory(
                    new ServerRequestCreator($factory, $factory, $factory, $factory)
                );
            },
            ContainerEntryOwner::Framework,
            self::class
        );
        $container->singleton(
            ResponseEmitter::class,
            static fn (): ResponseEmitter => new ResponseEmitter(),
            ContainerEntryOwner::Framework,
            self::class
        );
        $container->singleton(
            ErrorResponseFactory::class,
            static function (ContainerInterface $container): ErrorResponseFactory {
                $config = ContainerAccessor::get($container, Config::class);

                return new ErrorResponseFactory(
                    ContainerAccessor::get($container, ResponseFactoryInterface::class),
                    ContainerAccessor::get($container, StreamFactoryInterface::class),
                    (bool) $config->get('app.debug', false)
                );
            },
            ContainerEntryOwner::Framework,
            self::class
        );
        $container->singleton(
            ErrorHandlingMiddleware::class,
            static function (ContainerInterface $container): ErrorHandlingMiddleware {
                return new ErrorHandlingMiddleware(
                    ContainerAccessor::get($container, ErrorResponseFactory::class)
                );
            },
            ContainerEntryOwner::Framework,
            self::class
        );
    }
}
