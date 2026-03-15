<?php

declare(strict_types=1);

namespace Framework\Tests\Http;

use Framework\Http\ResponseEmitter;
use Framework\Tests\Support\FrameworkTestCase;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

/** @psalm-suppress UnusedClass */
final class ResponseEmitterTest extends FrameworkTestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testEmitterWritesStatusCodeAndRewoundBody(): void
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
}
