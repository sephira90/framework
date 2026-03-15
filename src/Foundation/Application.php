<?php

declare(strict_types=1);

namespace Framework\Foundation;

use Framework\Http\MiddlewareDispatcher;
use Framework\Http\MiddlewareResolver;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Верхнеуровневый HTTP kernel фреймворка.
 *
 * Application не знает о маршрутах напрямую и не собирает runtime. Его роль:
 * принять request, обернуть route dispatcher в глобальный middleware stack и
 * вернуть итоговый response как PSR-15 RequestHandler.
 */
final readonly class Application implements RequestHandlerInterface
{
    /**
     * @param list<class-string<MiddlewareInterface>|MiddlewareInterface> $globalMiddleware
     */
    public function __construct(
        private RequestHandlerInterface $routeDispatcher,
        private MiddlewareResolver $middlewareResolver,
        private array $globalMiddleware,
    ) {
    }

    /**
     * Прогоняет request через глобальные middleware и передаёт управление в
     * route dispatcher как внутренний handler пайплайна.
     */
    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $dispatcher = new MiddlewareDispatcher(
            $this->globalMiddleware,
            $this->middlewareResolver,
            $this->routeDispatcher
        );

        return $dispatcher->handle($request);
    }
}
