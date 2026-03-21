<?php

declare(strict_types=1);

namespace Framework\Tests\Foundation;

use Framework\Config\InvalidConfigurationException;
use Framework\Foundation\ApplicationFactory;
use Framework\Foundation\HttpRuntime;
use Framework\Tests\Support\Fixtures\ExplodingHandler;
use Framework\Tests\Support\Fixtures\GlobalOneMiddleware;
use Framework\Tests\Support\Fixtures\GlobalTwoMiddleware;
use Framework\Tests\Support\Fixtures\HelloHandler;
use Framework\Tests\Support\Fixtures\RouteMiddleware;
use Framework\Tests\Support\Fixtures\ShortCircuitMiddleware;
use Framework\Tests\Support\Fixtures\StackHandler;
use Framework\Tests\Support\FrameworkTestCase;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Psr\Http\Message\ResponseInterface;
use stdClass;

/** @psalm-suppress UnusedClass */
final class ApplicationFactoryTest extends FrameworkTestCase
{
    public function testApplicationFactoryBuildsRuntimeForClassAndCallableHandlers(): void
    {
        $runtime = $this->createRuntime(
            <<<'PHP'
<?php

declare(strict_types=1);

use Framework\Routing\RouteAttributes;
use Framework\Routing\RouteCollector;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

return static function (RouteCollector $routes): void {
    $routes->get('/hello', 'Framework\\Tests\\Support\\Fixtures\\HelloHandler');
    $routes->get('/callable', static function (ServerRequestInterface $request): ResponseInterface {
        return new Response(200, ['Content-Type' => 'text/plain; charset=utf-8'], 'callable');
    });
    $routes->get('/users/{id}', static function (ServerRequestInterface $request): ResponseInterface {
        $parameters = $request->getAttribute(RouteAttributes::PARAMS, []);
        $id = is_array($parameters) && is_string($parameters['id'] ?? null) ? $parameters['id'] : 'missing';

        return new Response(200, ['Content-Type' => 'text/plain; charset=utf-8'], $id);
    });
};
PHP,
            singletons: [
                HelloHandler::class => HelloHandler::class,
            ]
        );

        self::assertSame('hello', $this->handle($runtime, 'GET', '/hello'));
        self::assertSame('callable', $this->handle($runtime, 'GET', '/callable'));
        self::assertSame('42', $this->handle($runtime, 'GET', '/users/42'));
    }

    public function testApplicationExecutesGlobalAndRouteMiddlewareInOrder(): void
    {
        $runtime = $this->createRuntime(
            <<<'PHP'
<?php

declare(strict_types=1);

use Framework\Routing\RouteCollector;

return static function (RouteCollector $routes): void {
    $routes->get('/stack', 'Framework\\Tests\\Support\\Fixtures\\StackHandler', [
        'Framework\\Tests\\Support\\Fixtures\\RouteMiddleware',
    ]);
};
PHP,
            middleware: [
                GlobalOneMiddleware::class,
                GlobalTwoMiddleware::class,
            ],
            singletons: [
                GlobalOneMiddleware::class => GlobalOneMiddleware::class,
                GlobalTwoMiddleware::class => GlobalTwoMiddleware::class,
                RouteMiddleware::class => RouteMiddleware::class,
                StackHandler::class => StackHandler::class,
            ]
        );

        self::assertSame('global-one>global-two>route>handler', $this->handle($runtime, 'GET', '/stack'));
    }

    public function testApplicationSupportsRouteGroupsWithInheritedMiddleware(): void
    {
        $runtime = $this->createRuntime(
            <<<'PHP'
<?php

declare(strict_types=1);

use Framework\Routing\RouteCollector;

return static function (RouteCollector $routes): void {
    $routes->group('/api', static function (RouteCollector $routes): void {
        $routes->group('/v1', static function (RouteCollector $routes): void {
            $routes->get('/stack', 'Framework\\Tests\\Support\\Fixtures\\StackHandler', [
                'Framework\\Tests\\Support\\Fixtures\\RouteMiddleware',
            ])->name('api.stack');
        }, [
            'Framework\\Tests\\Support\\Fixtures\\GlobalTwoMiddleware',
        ]);
    }, [
        'Framework\\Tests\\Support\\Fixtures\\GlobalOneMiddleware',
    ]);
};
PHP,
            singletons: [
                GlobalOneMiddleware::class => GlobalOneMiddleware::class,
                GlobalTwoMiddleware::class => GlobalTwoMiddleware::class,
                RouteMiddleware::class => RouteMiddleware::class,
                StackHandler::class => StackHandler::class,
            ]
        );

        self::assertSame('global-one>global-two>route>handler', $this->handle($runtime, 'GET', '/api/v1/stack'));
    }

    public function testApplicationSupportsShortCircuitMiddleware(): void
    {
        $runtime = $this->createRuntime(
            <<<'PHP'
<?php

declare(strict_types=1);

use Framework\Routing\RouteCollector;

return static function (RouteCollector $routes): void {
    $routes->get('/short', 'Framework\\Tests\\Support\\Fixtures\\HelloHandler', [
        'Framework\\Tests\\Support\\Fixtures\\ShortCircuitMiddleware',
    ]);
};
PHP,
            singletons: [
                HelloHandler::class => HelloHandler::class,
                ShortCircuitMiddleware::class => ShortCircuitMiddleware::class,
            ]
        );

        $response = $this->response($runtime, 'GET', '/short');

        self::assertSame(204, $response->getStatusCode());
        self::assertSame('short-circuit', (string) $response->getBody());
    }

    public function testApplicationReturns404And405Responses(): void
    {
        $runtime = $this->createRuntime(
            <<<'PHP'
<?php

declare(strict_types=1);

use Framework\Routing\RouteCollector;

return static function (RouteCollector $routes): void {
    $routes->get('/hello', 'Framework\\Tests\\Support\\Fixtures\\HelloHandler');
};
PHP,
            singletons: [
                HelloHandler::class => HelloHandler::class,
            ]
        );

        $notFound = $this->response($runtime, 'GET', '/missing');
        $methodNotAllowed = $this->response($runtime, 'POST', '/hello');

        self::assertSame(404, $notFound->getStatusCode());
        self::assertSame('Not Found', (string) $notFound->getBody());
        self::assertSame(405, $methodNotAllowed->getStatusCode());
        self::assertSame('Method Not Allowed', (string) $methodNotAllowed->getBody());
        self::assertSame(['GET, HEAD'], $methodNotAllowed->getHeader('Allow'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testApplicationSuppressesBodyEmissionForHeadRequests(): void
    {
        $runtime = $this->createRuntime(
            <<<'PHP'
<?php

declare(strict_types=1);

use Framework\Routing\RouteCollector;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

return static function (RouteCollector $routes): void {
    $routes->get('/status', static function (ServerRequestInterface $request): ResponseInterface {
        return new Response(200, ['Content-Type' => 'text/plain; charset=utf-8'], 'payload');
    });
};
PHP
        );

        $factory = new Psr17Factory();
        $request = $factory->createServerRequest('HEAD', '/status');
        $response = $runtime->application()->handle($request);

        ob_start();
        $runtime->responseEmitter()->emit($response, emitBody: strcasecmp($request->getMethod(), 'HEAD') !== 0);
        $output = ob_get_clean();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('payload', (string) $response->getBody());
        self::assertSame('', $output);
        self::assertSame(200, http_response_code());
    }

    public function testApplicationHidesExceptionDetailsOutsideDebugMode(): void
    {
        $runtime = $this->createRuntime(
            <<<'PHP'
<?php

declare(strict_types=1);

use Framework\Routing\RouteCollector;

return static function (RouteCollector $routes): void {
    $routes->get('/explode', 'Framework\\Tests\\Support\\Fixtures\\ExplodingHandler');
};
PHP,
            debug: false,
            singletons: [
                ExplodingHandler::class => ExplodingHandler::class,
            ]
        );

        $response = $this->response($runtime, 'GET', '/explode');

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('Internal Server Error', (string) $response->getBody());
    }

    public function testApplicationExposesExceptionDetailsInDebugMode(): void
    {
        $runtime = $this->createRuntime(
            <<<'PHP'
<?php

declare(strict_types=1);

use Framework\Routing\RouteCollector;

return static function (RouteCollector $routes): void {
    $routes->get('/explode', 'Framework\\Tests\\Support\\Fixtures\\ExplodingHandler');
};
PHP,
            debug: true,
            singletons: [
                ExplodingHandler::class => ExplodingHandler::class,
            ]
        );

        $response = $this->response($runtime, 'GET', '/explode');

        self::assertSame(500, $response->getStatusCode());
        self::assertStringContainsString('RuntimeException: boom', (string) $response->getBody());
    }

    public function testApplicationRendersControlledHttpExceptionsFromHandlers(): void
    {
        $runtime = $this->createRuntime(
            <<<'PHP'
<?php

declare(strict_types=1);

use Framework\Http\Exception\ForbiddenException;
use Framework\Routing\RouteCollector;

return static function (RouteCollector $routes): void {
    $routes->get('/forbidden', static function (): never {
        throw new ForbiddenException('Access denied');
    });
};
PHP
        );

        $response = $this->response($runtime, 'GET', '/forbidden');

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('Access denied', (string) $response->getBody());
    }

    public function testApplicationFactoryFailsWhenRoutesFileIsMissing(): void
    {
        $basePath = $this->createTempDirectory();

        $this->writeFile($basePath, 'config/app.php', $this->configSource(routesPath: 'routes/missing.php'));

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('was not found');

        ApplicationFactory::createRuntime($basePath);
    }

    public function testApplicationFactoryFailsWhenRoutesFileDoesNotReturnCallableRegistrar(): void
    {
        $basePath = $this->createTempDirectory();

        $this->writeFile($basePath, 'config/app.php', $this->configSource());
        $this->writeFile($basePath, 'routes/web.php', <<<'PHP'
<?php

declare(strict_types=1);

return [];
PHP);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('must return a callable registrar');

        ApplicationFactory::createRuntime($basePath);
    }

    public function testApplicationFactoryFailsWhenGlobalMiddlewareClassIsNotAMiddleware(): void
    {
        $basePath = $this->createTempDirectory();

        $this->writeFile($basePath, 'config/app.php', $this->configSource(middleware: [stdClass::class]));
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

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('must implement MiddlewareInterface');

        ApplicationFactory::createRuntime($basePath);
    }

    /**
     * @param list<class-string> $middleware
     * @param array<class-string, class-string> $singletons
     */
    private function createRuntime(
        string $routesSource,
        bool $debug = false,
        array $middleware = [],
        array $singletons = [],
    ): HttpRuntime {
        $basePath = $this->createTempDirectory();

        $this->writeFile($basePath, 'config/app.php', $this->configSource($debug, $middleware, $singletons));
        $this->writeFile($basePath, 'routes/web.php', $routesSource);

        return ApplicationFactory::createRuntime($basePath);
    }

    /**
     * @param list<class-string> $middleware
     * @param array<class-string, class-string> $singletons
     */
    private function configSource(
        bool $debug = false,
        array $middleware = [],
        array $singletons = [],
        string $routesPath = 'routes/web.php',
    ): string {
        $config = [
            'app' => [
                'name' => 'Framework Test',
                'env' => 'testing',
                'debug' => $debug,
            ],
            'routes' => $routesPath,
            'middleware' => $middleware,
            'container' => [
                'bindings' => [],
                'singletons' => $singletons,
                'aliases' => [],
            ],
        ];

        return "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($config, true) . ";\n";
    }

    private function handle(HttpRuntime $runtime, string $method, string $path): string
    {
        return (string) $this->response($runtime, $method, $path)->getBody();
    }

    private function response(HttpRuntime $runtime, string $method, string $path): ResponseInterface
    {
        $factory = new Psr17Factory();
        $request = $factory->createServerRequest($method, $path);

        return $runtime->application()->handle($request);
    }
}
