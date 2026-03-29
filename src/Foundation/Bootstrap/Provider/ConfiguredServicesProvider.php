<?php

declare(strict_types=1);

namespace Framework\Foundation\Bootstrap\Provider;

use Framework\Foundation\Bootstrap\BootstrapBuilder;
use Framework\Foundation\Bootstrap\ConfiguredContainerConfigValidator;
use Framework\Foundation\Bootstrap\ConfiguredServicesRegistrar;
use Framework\Foundation\Bootstrap\ServiceProviderInterface;
use Override;

/**
 * Применяет user-defined container configuration поверх extensible core services.
 */
final readonly class ConfiguredServicesProvider implements ServiceProviderInterface
{
    public function __construct(
        private ConfiguredContainerConfigValidator $validator = new ConfiguredContainerConfigValidator(),
        private ConfiguredServicesRegistrar $registrar = new ConfiguredServicesRegistrar(),
    ) {
    }

    #[Override]
    public function register(BootstrapBuilder $builder): void
    {
        $this->registrar->register(
            $builder->containerBuilder(),
            $this->validator->resolve($builder->config())
        );
    }
}
