<?php

declare(strict_types=1);

namespace Framework\Http\Middleware;

use Framework\Http\ErrorResponseFactory;
use Framework\Http\Exception\HttpException;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

/**
 * Единая error boundary для всего HTTP execution path.
 */
final readonly class ErrorHandlingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ErrorResponseFactory $errorResponseFactory,
    ) {
    }

    /**
     * Перехватывает любое необработанное исключение и преобразует его в
     * HTTP response через ErrorResponseFactory.
     */
    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (HttpException $exception) {
            return $this->errorResponseFactory->fromHttpException($exception);
        } catch (Throwable $throwable) {
            return $this->errorResponseFactory->internalServerError($throwable);
        }
    }
}
