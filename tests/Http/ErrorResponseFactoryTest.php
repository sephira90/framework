<?php

declare(strict_types=1);

namespace Framework\Tests\Http;

use Framework\Http\ErrorResponseFactory;
use Framework\Tests\Support\FrameworkTestCase;
use Nyholm\Psr7\Factory\Psr17Factory;
use RuntimeException;

/** @psalm-suppress UnusedClass */
final class ErrorResponseFactoryTest extends FrameworkTestCase
{
    public function testNotFoundProducesPlainText404Response(): void
    {
        $response = $this->factory(debug: false)->notFound();

        self::assertSame(404, $response->getStatusCode());
        self::assertSame(['text/plain; charset=utf-8'], $response->getHeader('Content-Type'));
        self::assertSame('Not Found', (string) $response->getBody());
    }

    public function testMethodNotAllowedProducesAllowHeader(): void
    {
        $response = $this->factory(debug: false)->methodNotAllowed(['POST', 'GET']);

        self::assertSame(405, $response->getStatusCode());
        self::assertSame(['POST, GET'], $response->getHeader('Allow'));
        self::assertSame('Method Not Allowed', (string) $response->getBody());
    }

    public function testInternalServerErrorHidesDetailsOutsideDebugMode(): void
    {
        $response = $this->factory(debug: false)->internalServerError(new RuntimeException('boom'));

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('Internal Server Error', (string) $response->getBody());
    }

    public function testInternalServerErrorIncludesDetailsInDebugMode(): void
    {
        $response = $this->factory(debug: true)->internalServerError(new RuntimeException('boom'));
        $body = (string) $response->getBody();

        self::assertSame(500, $response->getStatusCode());
        self::assertStringContainsString('RuntimeException: boom', $body);
        self::assertStringContainsString('ErrorResponseFactoryTest.php', $body);
    }

    private function factory(bool $debug): ErrorResponseFactory
    {
        $psr17Factory = new Psr17Factory();

        return new ErrorResponseFactory($psr17Factory, $psr17Factory, $debug);
    }
}
