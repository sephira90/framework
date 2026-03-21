<?php

declare(strict_types=1);

namespace Framework\Http;

/**
 * Адаптер над стандартными PHP/SAPI primitives для emission response.
 */
final class NativeResponseEmitterSapi implements ResponseEmitterSapiInterface
{
    #[\Override]
    public function headersSent(): bool
    {
        return headers_sent();
    }

    #[\Override]
    public function clearActiveOutputBufferIfPossible(): void
    {
        if (ob_get_level() === 0) {
            return;
        }

        /** @var list<array<string, mixed>> $status */
        $status = ob_get_status(true);
        $lastIndex = array_key_last($status);

        if ($lastIndex === null) {
            return;
        }

        /** @var array{flags?: mixed} $activeBuffer */
        $activeBuffer = $status[$lastIndex];

        if (
            isset($activeBuffer['flags'])
            && is_int($activeBuffer['flags'])
            && ($activeBuffer['flags'] & PHP_OUTPUT_HANDLER_CLEANABLE) !== 0
        ) {
            ob_clean();
        }
    }

    #[\Override]
    public function sendStatusLine(string $protocolVersion, int $statusCode, string $reasonPhrase): void
    {
        $statusLine = sprintf('HTTP/%s %d', $protocolVersion, $statusCode);

        if ($reasonPhrase !== '') {
            $statusLine .= ' ' . $reasonPhrase;
        }

        header($statusLine, true, $statusCode);
    }

    #[\Override]
    public function sendHeader(string $name, string $value, bool $replace = false): void
    {
        header(sprintf('%s: %s', $name, $value), $replace);
    }
}
