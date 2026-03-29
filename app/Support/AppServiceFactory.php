<?php

declare(strict_types=1);

namespace App\Support;

use App\Console\Command\AboutCommand;
use App\Http\Handler\HomeHandler;
use Framework\Config\Config;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Exportable service factories used by the default project skeleton.
 *
 * Static factory references keep the starter app compatible with
 * `config:cache` without introducing container magic.
 *
 * @psalm-suppress PossiblyUnusedMethod
 */
final class AppServiceFactory
{
    /** @psalm-suppress PossiblyUnusedMethod */
    public static function makeAboutCommand(ContainerInterface $container): AboutCommand
    {
        /** @var Config $config */
        $config = $container->get(Config::class);

        return new AboutCommand($config);
    }

    /** @psalm-suppress PossiblyUnusedMethod */
    public static function makeHomeHandler(ContainerInterface $container): HomeHandler
    {
        /** @var ResponseFactoryInterface $responseFactory */
        $responseFactory = $container->get(ResponseFactoryInterface::class);
        /** @var StreamFactoryInterface $streamFactory */
        $streamFactory = $container->get(StreamFactoryInterface::class);

        return new HomeHandler(
            $responseFactory,
            $streamFactory
        );
    }
}
