<?php

declare(strict_types=1);

namespace Framework\Foundation\Bootstrap\Provider;

use Framework\Config\Config;
use Framework\Container\ContainerEntryOwner;
use Framework\Foundation\Bootstrap\BootstrapBuilder;
use Framework\Foundation\Bootstrap\ServiceProviderInterface;
use Override;

/**
 * Регистрирует действительно shared bootstrap services для любых runtime axes.
 */
final class SharedServicesProvider implements ServiceProviderInterface
{
    #[Override]
    public function register(BootstrapBuilder $builder): void
    {
        $builder->containerBuilder()->singleton(
            Config::class,
            $builder->config(),
            ContainerEntryOwner::Framework,
            self::class
        );
    }
}
