<?php

declare(strict_types=1);

namespace Framework\Http\Exception;

use RuntimeException;
use Throwable;

/**
 * Базовое исключение для осознанных HTTP-ошибок.
 *
 * Такие исключения выбрасываются тогда, когда код уже знает клиентскую
 * семантику сбоя: нужный status code, текст ответа и, при необходимости,
 * дополнительные headers.
 */
abstract class HttpException extends RuntimeException
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        string $message = '',
        private readonly array $headers = [],
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message !== '' ? $message : $this->defaultMessage(), $code, $previous);
    }

    abstract public function statusCode(): int;

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    abstract protected function defaultMessage(): string;
}
