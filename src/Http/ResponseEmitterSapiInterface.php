<?php

declare(strict_types=1);

namespace Framework\Http;

/**
 * Узкий seam над PHP/SAPI-функциями, которые использует ResponseEmitter.
 */
interface ResponseEmitterSapiInterface
{
    public function headersSent(): bool;

    public function clearActiveOutputBufferIfPossible(): void;

    public function sendStatusLine(string $protocolVersion, int $statusCode, string $reasonPhrase): void;

    public function sendHeader(string $name, string $value, bool $replace = false): void;
}
