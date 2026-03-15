<?php

declare(strict_types=1);

namespace App\Http\Handler;

use Override;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Минимальный прикладной handler стартового маршрута.
 *
 * Нужен как самый простой пример class-based handler, который получает
 * зависимости через контейнер и возвращает PSR-7 response.
 */
final readonly class HomeHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
    ) {
    }

    /**
     * Возвращает текстовый ответ, подтверждающий, что runtime собран и работает.
     */
    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->responseFactory
            ->createResponse(200)
            ->withHeader('Content-Type', 'text/plain; charset=utf-8')
            ->withBody($this->streamFactory->createStream('Framework v0 is running.'));
    }
}
