<?php

declare(strict_types=1);

namespace Framework\Http;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Исполняет middleware pipeline в PSR-15 стиле.
 *
 * Dispatcher работает через immutable offset-based recursion: каждый шаг
 * создаёт следующий handler, который представляет "оставшуюся часть" стека.
 * Это упрощает модель исполнения и устраняет скрытое mutable state.
 */
final class MiddlewareDispatcher implements RequestHandlerInterface
{
    /**
     * @param list<class-string<MiddlewareInterface>|MiddlewareInterface> $middlewareDefinitions
     */
    public function __construct(
        private readonly array $middlewareDefinitions,
        private readonly MiddlewareResolver $resolver,
        private readonly RequestHandlerInterface $fallbackHandler,
        private readonly int $offset = 0,
    ) {
    }

    /**
     * Запускает текущий middleware или делегирует управление fallback handler'у,
     * если стек уже исчерпан.
     */
    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $definition = $this->middlewareDefinitions[$this->offset] ?? null;

        if ($definition === null) {
            return $this->fallbackHandler->handle($request);
        }

        $middleware = $this->resolver->resolve($definition);
        $next = new self($this->middlewareDefinitions, $this->resolver, $this->fallbackHandler, $this->offset + 1);

        return $middleware->process($request, $next);
    }
}
