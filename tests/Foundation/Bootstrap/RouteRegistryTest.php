<?php

declare(strict_types=1);

namespace Framework\Tests\Foundation\Bootstrap;

use Framework\Foundation\Bootstrap\BootstrapStateException;
use Framework\Foundation\Bootstrap\RouteRegistry;
use Framework\Routing\RouteCollection;
use Framework\Tests\Support\FrameworkTestCase;

/** @psalm-suppress UnusedClass */
final class RouteRegistryTest extends FrameworkTestCase
{
    public function testRouteRegistryThrowsBeforeInitialization(): void
    {
        $registry = new RouteRegistry();

        self::assertFalse($registry->isInitialized());

        $this->expectException(BootstrapStateException::class);
        $this->expectExceptionMessage('Route registry has not been initialized yet.');

        $registry->routes();
    }

    public function testRouteRegistryRejectsRepeatedInitialization(): void
    {
        $registry = new RouteRegistry();
        $registry->initialize(new RouteCollection());

        self::assertTrue($registry->isInitialized());

        $this->expectException(BootstrapStateException::class);
        $this->expectExceptionMessage('Route registry has already been initialized.');

        $registry->initialize(new RouteCollection());
    }
}
