<?php

declare(strict_types=1);

namespace Framework\Foundation;

use Framework\Config\Config;
use Framework\Config\ProjectConfigLoader;
use Framework\Container\ContainerBuilder;
use Framework\Container\ContainerInspectionSnapshot;
use Framework\Foundation\Bootstrap\BootstrapBuilder;
use Framework\Foundation\Bootstrap\Bootstrapper;
use Framework\Foundation\Bootstrap\ContainerAccessor;
use Framework\Foundation\Bootstrap\Provider\ConfiguredServicesProvider;
use Framework\Foundation\Bootstrap\Provider\ConsoleCommandsProvider;
use Framework\Foundation\Bootstrap\Provider\ConsoleKernelProvider;
use Framework\Foundation\Bootstrap\Provider\SharedServicesProvider;
use Framework\Foundation\Bootstrap\ServiceProviderInterface;

/**
 * Тонкий bootstrap-orchestrator console runtime.
 *
 * Помимо обычной сборки runtime фабрика умеет строить read-only inspection
 * snapshot console container graph'а. Этот path intentionally останавливается
 * на register phase и не резолвит services.
 */
final class ConsoleApplicationFactory
{
    public static function createRuntime(string $basePath): ConsoleRuntime
    {
        $config = (new ProjectConfigLoader())->loadRuntime($basePath);
        $container = self::bootstrapper()->bootstrap($basePath, $config);

        return ContainerAccessor::get($container, ConsoleRuntime::class);
    }

    public static function createRecoveryRuntime(string $basePath): ConsoleRuntime
    {
        $config = (new ProjectConfigLoader())->loadRecovery($basePath);
        $container = self::bootstrapper()->bootstrap($basePath, $config);

        return ContainerAccessor::get($container, ConsoleRuntime::class);
    }

    public static function inspectRuntime(string $basePath): ContainerInspectionSnapshot
    {
        return self::inspect($basePath, (new ProjectConfigLoader())->loadRuntime($basePath));
    }

    public static function inspectSource(string $basePath): ContainerInspectionSnapshot
    {
        return self::inspect($basePath, (new ProjectConfigLoader())->loadSource($basePath));
    }

    private static function bootstrapper(): Bootstrapper
    {
        return new Bootstrapper(self::providers());
    }

    /**
     * @return list<ServiceProviderInterface>
     */
    private static function providers(): array
    {
        return [
            new SharedServicesProvider(),
            new ConfiguredServicesProvider(),
            new ConsoleCommandsProvider(),
            new ConsoleKernelProvider(),
        ];
    }

    private static function inspect(string $basePath, Config $config): ContainerInspectionSnapshot
    {
        $builder = new BootstrapBuilder($basePath, $config, new ContainerBuilder());

        foreach (self::providers() as $provider) {
            $provider->register($builder);
        }

        return $builder->containerBuilder()->inspectionSnapshot();
    }
}
