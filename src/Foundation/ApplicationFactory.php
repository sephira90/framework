<?php

declare(strict_types=1);

namespace Framework\Foundation;

use Framework\Config\ProjectConfigLoader;
use Framework\Foundation\Bootstrap\Bootstrapper;
use Framework\Foundation\Bootstrap\ContainerAccessor;
use Framework\Foundation\Bootstrap\Provider\ConfiguredServicesProvider;
use Framework\Foundation\Bootstrap\Provider\HttpCoreServicesProvider;
use Framework\Foundation\Bootstrap\Provider\HttpKernelProvider;
use Framework\Foundation\Bootstrap\Provider\RoutingServiceProvider;
use Framework\Foundation\Bootstrap\Provider\SharedServicesProvider;

/**
 * Тонкий bootstrap-orchestrator framework runtime.
 *
 * После введения internal bootstrap providers фабрика отвечает только за верхний
 * orchestration path:
 *
 * 1. загружает окружение;
 * 2. читает конфигурацию;
 * 3. запускает fixed-order bootstrap lifecycle;
 * 4. возвращает уже собранный `HttpRuntime` из container-managed graph.
 */
final class ApplicationFactory
{
    /**
     * Собирает полный runtime приложения из корня проекта.
     */
    public static function createRuntime(string $basePath): HttpRuntime
    {
        $config = (new ProjectConfigLoader())->loadRuntime($basePath);
        $container = self::bootstrapper()->bootstrap($basePath, $config);

        return ContainerAccessor::get($container, HttpRuntime::class);
    }

    private static function bootstrapper(): Bootstrapper
    {
        return new Bootstrapper([
            new SharedServicesProvider(),
            new ConfiguredServicesProvider(),
            new HttpCoreServicesProvider(),
            new RoutingServiceProvider(),
            new HttpKernelProvider(),
        ]);
    }
}
