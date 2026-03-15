<?php

declare(strict_types=1);

namespace Framework\Http;

use Framework\Routing\Route;
use Framework\Routing\RouteAttributes;
use Framework\Routing\RouteMatchStatus;
use Framework\Routing\Router;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Соединяет router с route execution path.
 *
 * Его роль:
 *
 * - выполнить route matching;
 * - вернуть `404` или `405`, если route не найден;
 * - положить matched route и params в request attributes;
 * - запустить route-level middleware и handler.
 */
final readonly class RouteDispatcher implements RequestHandlerInterface
{
    public function __construct(
        private Router $router,
        private HandlerResolver $handlerResolver,
        private MiddlewareResolver $middlewareResolver,
        private ErrorResponseFactory $errorResponseFactory,
    ) {
    }

    /**
     * Выполняет верхнеуровневый dispatch по маршруту.
     */
    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $match = $this->router->match($request->getMethod(), $request->getUri()->getPath());

        return match ($match->status()) {
            RouteMatchStatus::NotFound => $this->errorResponseFactory->notFound(),
            RouteMatchStatus::MethodNotAllowed => $this->errorResponseFactory
                ->methodNotAllowed($match->allowedMethods()),
            RouteMatchStatus::Found => $this->dispatchMatchedRoute($request, $match->route(), $match->parameters()),
        };
    }

    /**
     * Запускает уже найденный маршрут как внутренний pipeline:
     * route attributes -> route middleware -> route handler.
     *
     * @param array<string, string> $parameters
     */
    private function dispatchMatchedRoute(
        ServerRequestInterface $request,
        Route $route,
        array $parameters,
    ): ResponseInterface {
        $request = $request
            ->withAttribute(RouteAttributes::ROUTE, $route)
            ->withAttribute(RouteAttributes::PARAMS, $parameters);

        $routeHandler = new RouteHandler($route, $this->handlerResolver);
        $dispatcher = new MiddlewareDispatcher($route->middleware(), $this->middlewareResolver, $routeHandler);

        return $dispatcher->handle($request);
    }
}
