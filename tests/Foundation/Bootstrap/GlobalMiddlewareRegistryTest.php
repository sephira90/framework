<?php

declare(strict_types=1);

namespace Framework\Tests\Foundation\Bootstrap;

use Framework\Foundation\Bootstrap\BootstrapStateException;
use Framework\Foundation\Bootstrap\GlobalMiddlewareRegistry;
use Framework\Tests\Support\Fixtures\GlobalOneMiddleware;
use Framework\Tests\Support\FrameworkTestCase;

/** @psalm-suppress UnusedClass */
final class GlobalMiddlewareRegistryTest extends FrameworkTestCase
{
    public function testGlobalMiddlewareRegistryThrowsBeforeInitialization(): void
    {
        $registry = new GlobalMiddlewareRegistry();

        self::assertFalse($registry->isInitialized());

        $this->expectException(BootstrapStateException::class);
        $this->expectExceptionMessage('Global middleware registry has not been initialized yet.');

        $registry->middleware();
    }

    public function testGlobalMiddlewareRegistryRejectsRepeatedInitialization(): void
    {
        $registry = new GlobalMiddlewareRegistry();
        $registry->initialize([GlobalOneMiddleware::class]);

        self::assertTrue($registry->isInitialized());

        $this->expectException(BootstrapStateException::class);
        $this->expectExceptionMessage('Global middleware registry has already been initialized.');

        $registry->initialize([GlobalOneMiddleware::class]);
    }
}
