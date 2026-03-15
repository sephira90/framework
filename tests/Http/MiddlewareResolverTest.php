<?php

declare(strict_types=1);

namespace Framework\Tests\Http;

use Framework\Container\ContainerBuilder;
use Framework\Http\Exception\InvalidMiddlewareException;
use Framework\Http\MiddlewareResolver;
use Framework\Tests\Support\Fixtures\GlobalOneMiddleware;
use Framework\Tests\Support\FrameworkTestCase;
use stdClass;

/** @psalm-suppress UnusedClass */
final class MiddlewareResolverTest extends FrameworkTestCase
{
    public function testReturnsMiddlewareInstancesAsIs(): void
    {
        $middleware = new GlobalOneMiddleware();
        $resolver = new MiddlewareResolver((new ContainerBuilder())->build());

        self::assertSame($middleware, $resolver->resolve($middleware));
    }

    public function testResolvesMiddlewareFromContainer(): void
    {
        $builder = new ContainerBuilder();
        $builder->singleton(GlobalOneMiddleware::class, GlobalOneMiddleware::class);

        $resolver = new MiddlewareResolver($builder->build());

        self::assertInstanceOf(GlobalOneMiddleware::class, $resolver->resolve(GlobalOneMiddleware::class));
    }

    public function testThrowsWhenResolvedMiddlewareIsInvalid(): void
    {
        $builder = new ContainerBuilder();
        $builder->singleton('invalid.middleware', new stdClass());

        $resolver = new MiddlewareResolver($builder->build());

        $this->expectException(InvalidMiddlewareException::class);
        $this->expectExceptionMessage('must implement MiddlewareInterface');

        $resolver->resolve('invalid.middleware');
    }
}
