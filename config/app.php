<?php

declare(strict_types=1);

use App\Http\Handler\HomeHandler;
use Framework\Config\Env;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

// Центральная конфигурация приложения v0. Здесь намеренно один файл, чтобы
// bootstrap оставался прозрачным, а сборка runtime — легко реконструируемой.
return [
    'app' => [
        'name' => Env::get('APP_NAME', 'Framework'),
        'env' => Env::get('APP_ENV', 'production'),
        'debug' => Env::bool('APP_DEBUG', false),
    ],
    'routes' => 'routes/web.php',
    'middleware' => [],
    'container' => [
        'bindings' => [],
        'singletons' => [
            HomeHandler::class => static function (ContainerInterface $container): HomeHandler {
                // Handler зависит от PSR factory interfaces, а не от concrete
                // response implementation.
                /** @var ResponseFactoryInterface $responseFactory */
                $responseFactory = $container->get(ResponseFactoryInterface::class);
                /** @var StreamFactoryInterface $streamFactory */
                $streamFactory = $container->get(StreamFactoryInterface::class);

                return new HomeHandler(
                    $responseFactory,
                    $streamFactory
                );
            },
        ],
        'aliases' => [],
    ],
];
