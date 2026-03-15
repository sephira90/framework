<?php

declare(strict_types=1);

namespace Framework\Tests\Http;

use Framework\Container\ContainerBuilder;
use Framework\Http\MiddlewareDispatcher;
use Framework\Http\MiddlewareResolver;
use Framework\Tests\Support\Fixtures\GlobalOneMiddleware;
use Framework\Tests\Support\Fixtures\GlobalTwoMiddleware;
use Framework\Tests\Support\Fixtures\ShortCircuitMiddleware;
use Framework\Tests\Support\Fixtures\StackHandler;
use Framework\Tests\Support\FrameworkTestCase;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Server\MiddlewareInterface;

/** @psalm-suppress UnusedClass */
final class MiddlewareDispatcherTest extends FrameworkTestCase
{
    public function testDispatcherExecutesMiddlewareInDeclaredOrder(): void
    {
        $dispatcher = $this->dispatcher([
            GlobalOneMiddleware::class,
            GlobalTwoMiddleware::class,
        ]);

        $response = $dispatcher->handle($this->request());

        self::assertSame('global-one>global-two>handler', (string) $response->getBody());
    }

    public function testDispatcherShortCircuitsWhenMiddlewareReturnsEarly(): void
    {
        $dispatcher = $this->dispatcher([
            ShortCircuitMiddleware::class,
            GlobalOneMiddleware::class,
        ]);

        $response = $dispatcher->handle($this->request());

        self::assertSame(204, $response->getStatusCode());
        self::assertSame('short-circuit', (string) $response->getBody());
    }

    public function testDispatcherFallsBackWhenStackIsEmpty(): void
    {
        $dispatcher = $this->dispatcher([]);

        $response = $dispatcher->handle($this->request());

        self::assertSame('handler', (string) $response->getBody());
    }

    /**
     * @param list<class-string<MiddlewareInterface>> $middlewareDefinitions
     */
    private function dispatcher(array $middlewareDefinitions): MiddlewareDispatcher
    {
        $builder = new ContainerBuilder();
        $builder->singleton(GlobalOneMiddleware::class, GlobalOneMiddleware::class);
        $builder->singleton(GlobalTwoMiddleware::class, GlobalTwoMiddleware::class);
        $builder->singleton(ShortCircuitMiddleware::class, ShortCircuitMiddleware::class);

        return new MiddlewareDispatcher(
            $middlewareDefinitions,
            new MiddlewareResolver($builder->build()),
            new StackHandler()
        );
    }

    private function request(): \Psr\Http\Message\ServerRequestInterface
    {
        return (new Psr17Factory())->createServerRequest('GET', '/stack');
    }
}
