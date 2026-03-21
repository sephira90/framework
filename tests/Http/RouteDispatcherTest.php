<?php

declare(strict_types=1);

namespace Framework\Tests\Http;

use Framework\Container\ContainerBuilder;
use Framework\Http\ErrorResponseFactory;
use Framework\Http\HandlerResolver;
use Framework\Http\MiddlewareResolver;
use Framework\Http\RouteDispatcher;
use Framework\Routing\Route;
use Framework\Routing\RouteAttributes;
use Framework\Routing\RouteCollection;
use Framework\Routing\RouteCollector;
use Framework\Tests\Support\Fixtures\HelloHandler;
use Framework\Tests\Support\Fixtures\RouteMiddleware;
use Framework\Tests\Support\FrameworkTestCase;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** @psalm-suppress UnusedClass */
final class RouteDispatcherTest extends FrameworkTestCase
{
    public function testRouteDispatcherReturnsNotFoundResponses(): void
    {
        $collector = new RouteCollector(new RouteCollection());

        $response = $this->dispatcher($collector)->handle($this->request('GET', '/missing'));

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('Not Found', (string) $response->getBody());
    }

    public function testRouteDispatcherReturnsMethodNotAllowedResponses(): void
    {
        $collector = new RouteCollector(new RouteCollection());
        $collector->get('/hello', HelloHandler::class);

        $response = $this->dispatcher(
            $collector,
            [HelloHandler::class => HelloHandler::class]
        )->handle($this->request('POST', '/hello'));

        self::assertSame(405, $response->getStatusCode());
        self::assertSame(['GET, HEAD'], $response->getHeader('Allow'));
        self::assertSame('Method Not Allowed', (string) $response->getBody());
    }

    public function testRouteDispatcherAddsRouteAttributesAndExecutesRouteMiddleware(): void
    {
        $collector = new RouteCollector(new RouteCollection());
        $collector->get(
            '/users/{id}',
            static function (ServerRequestInterface $request): ResponseInterface {
                /** @var mixed $routeAttribute */
                $routeAttribute = $request->getAttribute(RouteAttributes::ROUTE);
                /** @var mixed $parametersAttribute */
                $parametersAttribute = $request->getAttribute(RouteAttributes::PARAMS, []);
                /** @var mixed $stackAttribute */
                $stackAttribute = $request->getAttribute('stack', []);

                $routePath = $routeAttribute instanceof Route ? $routeAttribute->path() : 'missing';
                $id = 'missing';

                if (is_array($parametersAttribute)) {
                    /** @var array{id?: mixed} $routeParameters */
                    $routeParameters = $parametersAttribute;
                    /** @var mixed $parameterId */
                    $parameterId = $routeParameters['id'] ?? null;

                    if (is_string($parameterId)) {
                        $id = $parameterId;
                    }
                }

                $normalizedStack = is_array($stackAttribute)
                    ? implode(
                        '>',
                        array_values(
                            array_filter(
                                $stackAttribute,
                                static fn (mixed $entry): bool => is_string($entry)
                            )
                        )
                    )
                    : 'missing';

                return new Response(200, [], sprintf('%s|%s|%s', $routePath, $id, $normalizedStack));
            },
            [RouteMiddleware::class]
        );

        $response = $this->dispatcher(
            $collector,
            [RouteMiddleware::class => RouteMiddleware::class]
        )->handle($this->request('GET', '/users/42'));

        self::assertSame('/users/{id}|42|route', (string) $response->getBody());
    }

    public function testRouteDispatcherTreatsConstraintMismatchesAsNotFound(): void
    {
        $collector = new RouteCollector(new RouteCollection());
        $collector->get(
            '/users/{id:\d+}',
            static fn (): ResponseInterface => new Response(200, [], 'ok')
        );

        $response = $this->dispatcher($collector)->handle($this->request('GET', '/users/forty-two'));

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('Not Found', (string) $response->getBody());
    }

    /**
     * @param array<class-string, class-string> $singletons
     */
    private function dispatcher(RouteCollector $collector, array $singletons = []): RouteDispatcher
    {
        $builder = new ContainerBuilder();

        foreach ($singletons as $id => $concrete) {
            $builder->singleton($id, $concrete);
        }

        $container = $builder->build();
        $psr17Factory = new Psr17Factory();

        return new RouteDispatcher(
            new \Framework\Routing\Router($collector->collection()),
            new HandlerResolver($container),
            new MiddlewareResolver($container),
            new ErrorResponseFactory($psr17Factory, $psr17Factory, false)
        );
    }

    private function request(string $method, string $path): ServerRequestInterface
    {
        return (new Psr17Factory())->createServerRequest($method, $path);
    }
}
