<?php

declare(strict_types=1);

namespace Framework\Http;

use Closure;
use Framework\Http\Exception\InvalidHandlerException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Превращает handler definition маршрута в реально исполнимый обработчик.
 *
 * Допустимые формы handler'а в v0:
 *
 * - `Closure`;
 * - `class-string`, который контейнер умеет резолвить.
 */
final readonly class HandlerResolver
{
    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    /**
     * Разрешает handler и выполняет его, гарантируя `ResponseInterface` на
     * выходе либо explicit exception при нарушении контракта.
     */
    public function dispatch(ServerRequestInterface $request, Closure|string $handler): ResponseInterface
    {
        $resolved = is_string($handler) ? $this->container->get($handler) : $handler;

        if ($resolved instanceof RequestHandlerInterface) {
            return $resolved->handle($request);
        }

        if (!is_callable($resolved)) {
            throw new InvalidHandlerException(sprintf(
                'Resolved handler [%s] is not callable and does not implement RequestHandlerInterface.',
                is_object($resolved) ? $resolved::class : gettype($resolved)
            ));
        }

        $response = $resolved($request);

        if (!$response instanceof ResponseInterface) {
            throw new InvalidHandlerException(sprintf(
                'Handler [%s] must return a ResponseInterface, got [%s].',
                is_string($handler) ? $handler : 'closure',
                is_object($response) ? $response::class : gettype($response)
            ));
        }

        return $response;
    }
}
