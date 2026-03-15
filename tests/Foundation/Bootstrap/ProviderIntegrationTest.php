<?php

declare(strict_types=1);

namespace Framework\Tests\Foundation\Bootstrap;

use Framework\Config\Config;
use Framework\Container\ContainerException;
use Framework\Container\ContainerBuilder;
use Framework\Foundation\Application;
use Framework\Foundation\Bootstrap\BootstrapBuilder;
use Framework\Foundation\Bootstrap\BootstrapContext;
use Framework\Foundation\Bootstrap\Provider\ConfiguredServicesProvider;
use Framework\Foundation\Bootstrap\Provider\CoreServicesProvider;
use Framework\Foundation\Bootstrap\Provider\HttpKernelProvider;
use Framework\Foundation\Bootstrap\Provider\RoutingServiceProvider;
use Framework\Foundation\HttpRuntime;
use Framework\Http\RequestFactory;
use Framework\Http\ResponseEmitter;
use Framework\Routing\RouteMatchStatus;
use Framework\Routing\Router;
use Framework\Tests\Support\FrameworkTestCase;

/** @psalm-suppress UnusedClass */
final class ProviderIntegrationTest extends FrameworkTestCase
{
    public function testRoutingProviderRequiresBootBeforeRouterResolution(): void
    {
        $config = $this->config();
        $builder = new BootstrapBuilder($this->createTempDirectory(), $config, new ContainerBuilder());
        $provider = new RoutingServiceProvider();

        $provider->register($builder);

        $container = $builder->containerBuilder()->build();

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Route registry has not been initialized yet.');

        $container->get(Router::class);
    }

    public function testRoutingProviderBootInitializesRouterFromRoutesFile(): void
    {
        $basePath = $this->createTempDirectory();
        $this->writeRoutesFile($basePath);

        $config = $this->config();
        $builder = new BootstrapBuilder($basePath, $config, new ContainerBuilder());
        $provider = new RoutingServiceProvider();

        $provider->register($builder);

        $container = $builder->containerBuilder()->build();
        $provider->boot(new BootstrapContext($basePath, $config, $container));

        $router = $container->get(Router::class);
        self::assertInstanceOf(Router::class, $router);
        self::assertSame(RouteMatchStatus::Found, $router->match('GET', '/hello')->status());
    }

    public function testHttpKernelProviderRequiresBootBeforeApplicationResolution(): void
    {
        $basePath = $this->createTempDirectory();
        $this->writeRoutesFile($basePath);

        $config = $this->config();
        [$builder, $routing] = $this->registeredProviders($basePath, $config);
        $container = $builder->containerBuilder()->build();
        $context = new BootstrapContext($basePath, $config, $container);

        $routing->boot($context);

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Global middleware registry has not been initialized yet.');

        $container->get(Application::class);
    }

    public function testHttpKernelProviderBuildsContainerManagedRuntimeGraph(): void
    {
        $basePath = $this->createTempDirectory();
        $this->writeRoutesFile($basePath);

        $config = $this->config();
        [$builder, $routing, $http] = $this->registeredProviders($basePath, $config);
        $container = $builder->containerBuilder()->build();
        $context = new BootstrapContext($basePath, $config, $container);

        $routing->boot($context);
        $http->boot($context);

        $runtime = $container->get(HttpRuntime::class);

        self::assertInstanceOf(HttpRuntime::class, $runtime);
        self::assertSame($runtime, $container->get(HttpRuntime::class));
        self::assertSame($runtime->application(), $container->get(Application::class));
        self::assertSame($runtime->requestFactory(), $container->get(RequestFactory::class));
        self::assertSame($runtime->responseEmitter(), $container->get(ResponseEmitter::class));
    }

    /**
     * @return array{
     *     BootstrapBuilder,
     *     RoutingServiceProvider,
     *     HttpKernelProvider
     * }
     */
    private function registeredProviders(string $basePath, Config $config): array
    {
        $builder = new BootstrapBuilder($basePath, $config, new ContainerBuilder());
        $core = new CoreServicesProvider();
        $configured = new ConfiguredServicesProvider();
        $routing = new RoutingServiceProvider();
        $http = new HttpKernelProvider();

        $core->register($builder);
        $configured->register($builder);
        $routing->register($builder);
        $http->register($builder);

        return [$builder, $routing, $http];
    }

    private function config(): Config
    {
        return new Config([
            'app' => [
                'name' => 'Framework Test',
                'env' => 'testing',
                'debug' => false,
            ],
            'routes' => 'routes/web.php',
            'middleware' => [],
            'container' => [
                'bindings' => [],
                'singletons' => [],
                'aliases' => [],
            ],
        ]);
    }

    private function writeRoutesFile(string $basePath): void
    {
        $this->writeFile($basePath, 'routes/web.php', <<<'PHP'
<?php

declare(strict_types=1);

use Framework\Routing\RouteCollector;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

return static function (RouteCollector $routes): void {
    $routes->get('/hello', static function (ServerRequestInterface $request): ResponseInterface {
        return new Response(200, ['Content-Type' => 'text/plain; charset=utf-8'], 'hello');
    });
};
PHP);
    }
}
