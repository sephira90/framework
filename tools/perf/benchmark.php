<?php

declare(strict_types=1);

use Framework\Config\ProjectConfigLoader;
use Framework\Container\ContainerBuilder;
use Framework\Foundation\ApplicationFactory;
use Framework\Foundation\Bootstrap\Bootstrapper;
use Framework\Foundation\Bootstrap\ConfiguredContainerConfig;
use Framework\Foundation\Bootstrap\ConfiguredContainerConfigValidator;
use Framework\Foundation\Bootstrap\ConfiguredServicesRegistrar;
use Framework\Foundation\Bootstrap\Provider\ConfiguredServicesProvider;
use Framework\Foundation\Bootstrap\Provider\ConsoleCommandsProvider;
use Framework\Foundation\Bootstrap\Provider\ConsoleKernelProvider;
use Framework\Foundation\Bootstrap\Provider\HttpCoreServicesProvider;
use Framework\Foundation\Bootstrap\Provider\HttpKernelProvider;
use Framework\Foundation\Bootstrap\Provider\RoutingServiceProvider;
use Framework\Foundation\Bootstrap\Provider\SharedServicesProvider;
use Framework\Foundation\ConsoleApplicationFactory;
use Framework\Routing\Route;
use Framework\Routing\RouteCollection;
use Framework\Routing\RouteIndex;
use Framework\Routing\Router;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

/**
 * @param mixed $rawArgv
 * @return list<string>
 */
$normalizeArgv = static function (mixed $rawArgv): array {
    if (!is_array($rawArgv)) {
        return [];
    }

    $argv = [];

    foreach ($rawArgv as $value) {
        if (!is_scalar($value)) {
            continue;
        }

        $argv[] = (string) $value;
    }

    return $argv;
};

$argv = $normalizeArgv($_SERVER['argv'] ?? []);
$scenario = $argv[1] ?? 'all';
$basePath = dirname(__DIR__, 2);

$measure = static function (int $iterations, callable $callback): array {
    $callback();
    gc_collect_cycles();

    $start = hrtime(true);

    for ($index = 0; $index < $iterations; $index++) {
        $callback();
    }

    $elapsedNanoseconds = hrtime(true) - $start;
    $totalMilliseconds = $elapsedNanoseconds / 1_000_000;

    return [
        'total_ms' => $totalMilliseconds,
        'avg_ms' => fdiv((float) $totalMilliseconds, (float) $iterations),
    ];
};

$syntheticDefinitions = static function (string $prefix, int $count): array {
    $definitions = [];

    for ($index = 0; $index < $count; $index++) {
        $definitions[$prefix . '.' . $index] = static fn (): stdClass => new stdClass();
    }

    return $definitions;
};

$syntheticAliases = static function (int $count): array {
    $aliases = [];

    for ($index = 0; $index < $count; $index++) {
        $aliases['alias.' . $index] = 'singleton.' . $index;
    }

    return $aliases;
};

$bootstrapScenario = static function () use ($basePath, $measure): array {
    $configLoader = new ProjectConfigLoader();
    $config = $configLoader->loadSource($basePath);
    $httpBootstrapper = new Bootstrapper([
        new SharedServicesProvider(),
        new ConfiguredServicesProvider(),
        new HttpCoreServicesProvider(),
        new RoutingServiceProvider(),
        new HttpKernelProvider(),
    ]);
    $consoleBootstrapper = new Bootstrapper([
        new SharedServicesProvider(),
        new ConfiguredServicesProvider(),
        new ConsoleCommandsProvider(),
        new ConsoleKernelProvider(),
    ]);

    return [
        'config_load_runtime' => $measure(300, static function () use ($configLoader, $basePath): void {
            $configLoader->loadRuntime($basePath);
        }),
        'http_bootstrap_only' => $measure(300, static function () use (
            $httpBootstrapper,
            $basePath,
            $config
        ): void {
            $httpBootstrapper->bootstrap($basePath, $config);
        }),
        'console_bootstrap_only' => $measure(300, static function () use (
            $consoleBootstrapper,
            $basePath,
            $config
        ): void {
            $consoleBootstrapper->bootstrap($basePath, $config);
        }),
        'http_create_runtime' => $measure(300, static function () use ($basePath): void {
            ApplicationFactory::createRuntime($basePath);
        }),
        'console_create_runtime' => $measure(300, static function () use ($basePath): void {
            ConsoleApplicationFactory::createRuntime($basePath);
        }),
    ];
};

$registrationScenario = static function () use (
    $basePath,
    $measure,
    $syntheticDefinitions,
    $syntheticAliases
): array {
    $realConfig = (new ProjectConfigLoader())->loadSource($basePath);
    $validator = new ConfiguredContainerConfigValidator();
    $registrar = new ConfiguredServicesRegistrar();
    $syntheticConfig = new ConfiguredContainerConfig(
        $syntheticDefinitions('binding', 500),
        $syntheticDefinitions('singleton', 500),
        $syntheticAliases(500)
    );

    return [
        'configured_services_register_runtime_config' => $measure(
            2000,
            static function () use ($registrar, $validator, $realConfig): void {
                $registrar->register(new ContainerBuilder(), $validator->resolve($realConfig));
            }
        ),
        'configured_services_register_synthetic_500' => $measure(
            300,
            static function () use ($registrar, $syntheticConfig): void {
                $registrar->register(new ContainerBuilder(), $syntheticConfig);
            }
        ),
    ];
};

$routingScenario = static function () use ($measure): array {
    $staticCollection = new RouteCollection();
    $dynamicCollection = new RouteCollection();

    for ($index = 0; $index < 1000; $index++) {
        $staticCollection->add(new Route(['GET'], '/static-' . $index, 'static-handler-' . $index));
        $dynamicCollection->add(new Route(['GET'], '/resource-' . $index . '/{id}', 'dynamic-handler-' . $index));
    }

    $staticRouter = new Router(RouteIndex::fromRouteCollection($staticCollection));
    $dynamicRouter = new Router(RouteIndex::fromRouteCollection($dynamicCollection));

    return [
        'router_static_match_last_of_1000' => $measure(100000, static function () use ($staticRouter): void {
            $staticRouter->match('GET', '/static-999');
        }),
        'router_static_miss_1000' => $measure(100000, static function () use ($staticRouter): void {
            $staticRouter->match('GET', '/missing');
        }),
        'router_dynamic_match_last_of_1000' => $measure(10000, static function () use ($dynamicRouter): void {
            $dynamicRouter->match('GET', '/resource-999/42');
        }),
        'router_dynamic_miss_1000' => $measure(10000, static function () use ($dynamicRouter): void {
            $dynamicRouter->match('GET', '/missing/42');
        }),
    ];
};

$results = match ($scenario) {
    'bootstrap' => $bootstrapScenario(),
    'registration' => $registrationScenario(),
    'routing' => $routingScenario(),
    'all' => [
        ...$bootstrapScenario(),
        ...$registrationScenario(),
        ...$routingScenario(),
    ],
    default => null,
};

if ($results === null) {
    fwrite(
        STDERR,
        'Unsupported scenario [' . $scenario . ']. Use bootstrap, registration, routing, or all.' . PHP_EOL
    );
    exit(1);
}

foreach ($results as $name => $metrics) {
    printf(
        "%s total=%.2fms avg=%.6fms\n",
        $name,
        $metrics['total_ms'],
        $metrics['avg_ms']
    );
}
