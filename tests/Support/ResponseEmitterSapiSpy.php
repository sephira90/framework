<?php

declare(strict_types=1);

namespace Framework\Tests\Support;

use Framework\Http\ResponseEmitterSapiInterface;

final class ResponseEmitterSapiSpy implements ResponseEmitterSapiInterface
{
    public bool $headersAlreadySent = false;

    public int $bufferClearCalls = 0;

    public ?string $statusLine = null;

    /** @var list<string> */
    public array $headers = [];

    #[\Override]
    public function headersSent(): bool
    {
        return $this->headersAlreadySent;
    }

    #[\Override]
    public function clearActiveOutputBufferIfPossible(): void
    {
        $this->bufferClearCalls++;
    }

    #[\Override]
    public function sendStatusLine(string $protocolVersion, int $statusCode, string $reasonPhrase): void
    {
        $this->statusLine = sprintf(
            'HTTP/%s %d%s',
            $protocolVersion,
            $statusCode,
            $reasonPhrase !== '' ? ' ' . $reasonPhrase : ''
        );
    }

    #[\Override]
    public function sendHeader(string $name, string $value, bool $replace = false): void
    {
        unset($replace);

        $this->headers[] = sprintf('%s: %s', $name, $value);
    }
}
