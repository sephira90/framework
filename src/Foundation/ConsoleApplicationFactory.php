<?php

declare(strict_types=1);

namespace Framework\Foundation;

use Framework\Config\ConfigLoader;
use Framework\Config\EnvironmentLoader;
use Framework\Foundation\Bootstrap\Bootstrapper;
use Framework\Foundation\Bootstrap\ContainerAccessor;
use Framework\Foundation\Bootstrap\Provider\ConfiguredServicesProvider;
use Framework\Foundation\Bootstrap\Provider\ConsoleCommandsProvider;
use Framework\Foundation\Bootstrap\Provider\ConsoleKernelProvider;
use Framework\Foundation\Bootstrap\Provider\SharedServicesProvider;

/**
 * Тонкий bootstrap-orchestrator console runtime.
 */
final class ConsoleApplicationFactory
{
    public static function createRuntime(string $basePath): ConsoleRuntime
    {
        (new EnvironmentLoader())->load($basePath);

        $config = ConfigLoader::load($basePath . DIRECTORY_SEPARATOR . 'config');
        $container = self::bootstrapper()->bootstrap($basePath, $config);

        return ContainerAccessor::get($container, ConsoleRuntime::class);
    }

    private static function bootstrapper(): Bootstrapper
    {
        return new Bootstrapper([
            new SharedServicesProvider(),
            new ConfiguredServicesProvider(),
            new ConsoleCommandsProvider(),
            new ConsoleKernelProvider(),
        ]);
    }
}
