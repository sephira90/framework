<?php

declare(strict_types=1);

namespace Framework\Http;

use Framework\Http\Exception\InvalidMiddlewareException;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;

/**
 * Разрешает middleware definition до `MiddlewareInterface`.
 */
final readonly class MiddlewareResolver
{
    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    /**
     * Допускает либо готовый middleware instance, либо class-string, который
     * должен резолвиться через контейнер.
     */
    public function resolve(MiddlewareInterface|string $definition): MiddlewareInterface
    {
        $middleware = is_string($definition) ? $this->container->get($definition) : $definition;

        if (!$middleware instanceof MiddlewareInterface) {
            throw new InvalidMiddlewareException(sprintf(
                'Resolved middleware [%s] must implement MiddlewareInterface.',
                is_object($middleware) ? $middleware::class : gettype($middleware)
            ));
        }

        return $middleware;
    }
}
