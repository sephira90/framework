<?php

declare(strict_types=1);

namespace Framework\Http;

use Psr\Http\Message\ResponseInterface;

/**
 * Отправляет PSR-7 response в стандартный PHP/SAPI runtime.
 */
final class ResponseEmitter
{
    /**
     * Эмитит status, headers и body.
     *
     * Если headers уже отправлены, emitter прекращает работу, чтобы не
     * создавать вторичные ошибки на уровне PHP runtime.
     *
     * @param bool $emitBody Управляет transport-level body emission policy.
     *     Нужен для протокольных случаев вроде `HEAD`, где response object
     *     может содержать body, но сервер не должен отправлять его клиенту.
     */
    public function emit(ResponseInterface $response, bool $emitBody = true): void
    {
        if (headers_sent()) {
            return;
        }

        http_response_code($response->getStatusCode());

        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header(sprintf('%s: %s', $name, $value), false);
            }
        }

        $body = $response->getBody();

        if (!$emitBody) {
            return;
        }

        if ($body->isSeekable()) {
            $body->rewind();
        }

        while (!$body->eof()) {
            echo $body->read(8192);
        }
    }
}
