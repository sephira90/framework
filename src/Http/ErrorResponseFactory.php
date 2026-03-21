<?php

declare(strict_types=1);

namespace Framework\Http;

use Framework\Http\Exception\HttpException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Throwable;

/**
 * Строит стандартные текстовые error responses фреймворка.
 */
final readonly class ErrorResponseFactory
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
        private bool $debug,
    ) {
    }

    /**
     * Возвращает `404 Not Found`.
     */
    public function notFound(): ResponseInterface
    {
        return $this->textResponse(404, 'Not Found');
    }

    /**
     * Возвращает `405 Method Not Allowed` и устанавливает `Allow`.
     *
     * @param list<string> $allowedMethods
     */
    public function methodNotAllowed(array $allowedMethods): ResponseInterface
    {
        return $this->textResponse(405, 'Method Not Allowed', [
            'Allow' => implode(', ', $allowedMethods),
        ]);
    }

    /**
     * Строит response из контролируемого HTTP-исключения.
     */
    public function fromHttpException(HttpException $exception): ResponseInterface
    {
        return $this->textResponse(
            $exception->statusCode(),
            $exception->getMessage(),
            $exception->headers(),
        );
    }

    /**
     * Возвращает `500 Internal Server Error`.
     *
     * В debug-режиме тело ответа намеренно содержит диагностическую
     * информацию. В production-режиме ответ скрывает детали исключения.
     */
    public function internalServerError(Throwable $throwable): ResponseInterface
    {
        if (!$this->debug) {
            return $this->textResponse(500, 'Internal Server Error');
        }

        return $this->textResponse(500, implode(PHP_EOL, [
            sprintf('%s: %s', $throwable::class, $throwable->getMessage()),
            sprintf('In %s:%d', $throwable->getFile(), $throwable->getLine()),
            $throwable->getTraceAsString(),
        ]));
    }

    /**
     * Собирает текстовый response с фиксированным content type.
     *
     * @param array<string, string> $headers
     */
    private function textResponse(int $status, string $body, array $headers = []): ResponseInterface
    {
        $response = $this->responseFactory
            ->createResponse($status)
            ->withHeader('Content-Type', 'text/plain; charset=utf-8');

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response->withBody($this->streamFactory->createStream($body));
    }
}
