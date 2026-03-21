<?php

declare(strict_types=1);

namespace Framework\Tests\Http;

use Framework\Http\ErrorResponseFactory;
use Framework\Http\Exception\ForbiddenException;
use Framework\Http\Exception\MethodNotAllowedException;
use Framework\Http\Exception\NotFoundException;
use Framework\Http\Exception\UnprocessableEntityException;
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

    public function testFactoryBuildsResponsesFromControlledHttpExceptions(): void
    {
        $notFound = $this->factory(debug: false)->fromHttpException(new NotFoundException());
        $forbidden = $this->factory(debug: false)->fromHttpException(new ForbiddenException('Access denied'));
        $methodNotAllowedException = new MethodNotAllowedException(['post', 'get']);
        $methodNotAllowed = $this->factory(debug: false)->fromHttpException($methodNotAllowedException);
        $unprocessable = $this->factory(debug: false)->fromHttpException(
            new UnprocessableEntityException('Validation failed')
        );

        self::assertSame(404, $notFound->getStatusCode());
        self::assertSame('Not Found', (string) $notFound->getBody());

        self::assertSame(403, $forbidden->getStatusCode());
        self::assertSame('Access denied', (string) $forbidden->getBody());

        self::assertSame(['GET', 'HEAD', 'POST'], $methodNotAllowedException->allowedMethods());
        self::assertSame(405, $methodNotAllowed->getStatusCode());
        self::assertSame(['GET, HEAD, POST'], $methodNotAllowed->getHeader('Allow'));
        self::assertSame('Method Not Allowed', (string) $methodNotAllowed->getBody());

        self::assertSame(422, $unprocessable->getStatusCode());
        self::assertSame('Validation failed', (string) $unprocessable->getBody());
    }

    private function factory(bool $debug): ErrorResponseFactory
    {
        $psr17Factory = new Psr17Factory();

        return new ErrorResponseFactory($psr17Factory, $psr17Factory, $debug);
    }
}
