<?php

declare(strict_types=1);

namespace Framework\Http;

use Framework\Routing\Route;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Адаптер между Route и HandlerResolver.
 */
final readonly class RouteHandler implements RequestHandlerInterface
{
    public function __construct(
        private Route $route,
        private HandlerResolver $handlerResolver,
    ) {
    }

    /**
     * Делегирует исполнение handler definition текущего маршрута.
     */
    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->handlerResolver->dispatch($request, $this->route->handler());
    }
}
