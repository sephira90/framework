<?php

declare(strict_types=1);

namespace Framework\Tests\Http;

use Framework\Http\ResponseEmitter;
use Framework\Tests\Support\ResponseEmitterSapiSpy;
use Framework\Tests\Support\FrameworkTestCase;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

/** @psalm-suppress UnusedClass */
final class ResponseEmitterTest extends FrameworkTestCase
{
    public function testEmitterUsesExplicitStatusLineAndRewoundBody(): void
    {
        $factory = new Psr17Factory();
        $body = $factory->createStream('payload');
        $body->read(2);
        $response = $factory
            ->createResponse(402, 'Custom Payment Required')
            ->withHeader('Content-Type', 'text/plain; charset=utf-8')
            ->withBody($body);
        $sapi = new ResponseEmitterSapiSpy();

        ob_start();
        (new ResponseEmitter($sapi))->emit($response);
        $output = ob_get_clean();

        self::assertSame('payload', $output);
        self::assertSame(1, $sapi->bufferClearCalls);
        self::assertSame('HTTP/1.1 402 Custom Payment Required', $sapi->statusLine);
        self::assertSame(['Content-Type: text/plain; charset=utf-8'], $sapi->headers);
    }

    public function testEmitterSynthesizesContentLengthForSuppressedBodyWhenMissing(): void
    {
        $factory = new Psr17Factory();
        $response = $factory->createResponse(200)->withBody($factory->createStream('payload'));
        $sapi = new ResponseEmitterSapiSpy();

        ob_start();
        (new ResponseEmitter($sapi))->emit($response, emitBody: false);
        $output = ob_get_clean();

        self::assertSame('', $output);
        self::assertSame('HTTP/1.1 200 OK', $sapi->statusLine);
        self::assertSame(['Content-Length: 7'], $sapi->headers);
    }

    public function testEmitterPreservesExistingContentLengthWhenSuppressingBody(): void
    {
        $factory = new Psr17Factory();
        $response = $factory
            ->createResponse(200)
            ->withHeader('Content-Length', '999')
            ->withBody($factory->createStream('payload'));
        $sapi = new ResponseEmitterSapiSpy();

        ob_start();
        (new ResponseEmitter($sapi))->emit($response, emitBody: false);
        $output = ob_get_clean();

        self::assertSame('', $output);
        self::assertSame(['Content-Length: 999'], $sapi->headers);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testEmitterWritesStatusCodeAndRewoundBodyWithNativeSapi(): void
    {
        $factory = new Psr17Factory();
        $body = $factory->createStream('payload');
        $body->read(2);

        $response = $factory->createResponse(201)->withBody($body);

        ob_start();
        (new ResponseEmitter())->emit($response);
        $output = ob_get_clean();

        self::assertSame('payload', $output);
        self::assertSame(201, http_response_code());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testEmitterStreamsLargeBodiesCompletely(): void
    {
        $factory = new Psr17Factory();
        $payload = str_repeat('abcdefghij', 1200);
        $response = $factory->createResponse(200)->withBody($factory->createStream($payload));

        ob_start();
        (new ResponseEmitter())->emit($response);
        $output = ob_get_clean();

        self::assertSame($payload, $output);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testEmitterClearsActiveOutputBufferBeforeEmission(): void
    {
        $factory = new Psr17Factory();
        $response = $factory->createResponse(200)->withBody($factory->createStream('payload'));

        ob_start();
        echo 'noise';
        (new ResponseEmitter())->emit($response);
        $output = ob_get_clean();

        self::assertSame('payload', $output);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testEmitterCanSuppressBodyEmissionWithNativeSapi(): void
    {
        $factory = new Psr17Factory();
        $response = $factory->createResponse(200)->withBody($factory->createStream('payload'));

        ob_start();
        (new ResponseEmitter())->emit($response, emitBody: false);
        $output = ob_get_clean();

        self::assertSame('', $output);
        self::assertSame(200, http_response_code());
    }
}
