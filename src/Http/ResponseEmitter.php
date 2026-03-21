<?php

declare(strict_types=1);

namespace Framework\Http;

use Psr\Http\Message\ResponseInterface;

/**
 * Отправляет PSR-7 response в стандартный PHP/SAPI runtime.
 */
final class ResponseEmitter
{
    private readonly ResponseEmitterSapiInterface $sapi;

    public function __construct(?ResponseEmitterSapiInterface $sapi = null)
    {
        $this->sapi = $sapi ?? new NativeResponseEmitterSapi();
    }

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
        if ($this->sapi->headersSent()) {
            return;
        }

        $this->sapi->clearActiveOutputBufferIfPossible();

        if ($this->sapi->headersSent()) {
            return;
        }

        $body = $response->getBody();
        $headers = $response->getHeaders();

        if (
            !$emitBody
            && !$response->hasHeader('Content-Length')
            && ($bodySize = $body->getSize()) !== null
        ) {
            $headers['Content-Length'] = [(string) $bodySize];
        }

        $this->sapi->sendStatusLine(
            $response->getProtocolVersion(),
            $response->getStatusCode(),
            $response->getReasonPhrase()
        );

        foreach ($headers as $name => $values) {
            foreach ($values as $value) {
                $this->sapi->sendHeader((string) $name, $value);
            }
        }

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
