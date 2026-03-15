<?php

declare(strict_types=1);

namespace Framework\Tests\Http;

use Framework\Container\ContainerBuilder;
use Framework\Http\Exception\InvalidHandlerException;
use Framework\Http\HandlerResolver;
use Framework\Tests\Support\Fixtures\HelloHandler;
use Framework\Tests\Support\FrameworkTestCase;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use stdClass;

/** @psalm-suppress UnusedClass */
final class HandlerResolverTest extends FrameworkTestCase
{
    public function testDispatchesClosureHandlers(): void
    {
        $resolver = new HandlerResolver((new ContainerBuilder())->build());
        $request = $this->request();

        $response = $resolver->dispatch(
            $request,
            static fn (): Response => new Response(200, [], 'closure')
        );

        self::assertSame('closure', (string) $response->getBody());
    }

    public function testDispatchesRequestHandlersResolvedFromContainer(): void
    {
        $builder = new ContainerBuilder();
        $builder->singleton(HelloHandler::class, HelloHandler::class);

        $resolver = new HandlerResolver($builder->build());
        $response = $resolver->dispatch($this->request(), HelloHandler::class);

        self::assertSame('hello', (string) $response->getBody());
    }

    public function testThrowsWhenResolvedHandlerIsNotCallableOrRequestHandler(): void
    {
        $builder = new ContainerBuilder();
        $builder->singleton('invalid.handler', new stdClass());

        $resolver = new HandlerResolver($builder->build());

        $this->expectException(InvalidHandlerException::class);
        $this->expectExceptionMessage('is not callable and does not implement RequestHandlerInterface');

        $resolver->dispatch($this->request(), 'invalid.handler');
    }

    public function testThrowsWhenCallableHandlerDoesNotReturnAResponse(): void
    {
        $resolver = new HandlerResolver((new ContainerBuilder())->build());

        $this->expectException(InvalidHandlerException::class);
        $this->expectExceptionMessage('must return a ResponseInterface');

        $resolver->dispatch($this->request(), static fn (): stdClass => new stdClass());
    }

    private function request(): \Psr\Http\Message\ServerRequestInterface
    {
        return (new Psr17Factory())->createServerRequest('GET', '/test');
    }
}
