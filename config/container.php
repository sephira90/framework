<?php

declare(strict_types=1);

use App\Console\Command\AboutCommand;
use App\Http\Handler\HomeHandler;
use Framework\Config\Config;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

// Container slice живёт отдельно, чтобы сервисные bindings не смешивались с
// application metadata и HTTP settings.
return [
    'container' => [
        'bindings' => [],
        'singletons' => [
            AboutCommand::class => static function (ContainerInterface $container): AboutCommand {
                /** @var Config $config */
                $config = $container->get(Config::class);

                return new AboutCommand($config);
            },
            HomeHandler::class => static function (ContainerInterface $container): HomeHandler {
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
