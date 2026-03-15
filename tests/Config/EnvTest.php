<?php

declare(strict_types=1);

namespace Framework\Tests\Config;

use Framework\Config\Env;
use Framework\Tests\Support\FrameworkTestCase;

/** @psalm-suppress UnusedClass */
final class EnvTest extends FrameworkTestCase
{
    public function testGetPrefersEnvOverServerAndGetenv(): void
    {
        $key = 'FRAMEWORK_ENV_TEST_' . bin2hex(random_bytes(4));

        $_ENV[$key] = 'env';
        $_SERVER[$key] = 'server';
        putenv($key . '=getenv');

        try {
            self::assertSame('env', Env::get($key));
        } finally {
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);
        }
    }

    public function testGetFallsBackToServerWhenEnvIsMissing(): void
    {
        $key = 'FRAMEWORK_ENV_TEST_' . bin2hex(random_bytes(4));

        unset($_ENV[$key]);
        $_SERVER[$key] = 'server';
        putenv($key . '=getenv');

        try {
            self::assertSame('server', Env::get($key));
        } finally {
            unset($_SERVER[$key]);
            putenv($key);
        }
    }

    public function testGetFallsBackToGetenvWhenSuperglobalsMiss(): void
    {
        $key = 'FRAMEWORK_ENV_TEST_' . bin2hex(random_bytes(4));

        unset($_ENV[$key], $_SERVER[$key]);
        putenv($key . '=getenv');

        try {
            self::assertSame('getenv', Env::get($key));
        } finally {
            putenv($key);
        }
    }

    public function testBoolParsesKnownValuesAndUsesDefaultForUnknownOnes(): void
    {
        $key = 'FRAMEWORK_ENV_BOOL_' . bin2hex(random_bytes(4));

        unset($_ENV[$key], $_SERVER[$key]);

        try {
            putenv($key . '=yes');
            self::assertTrue(Env::bool($key, false));

            putenv($key . '=off');
            self::assertFalse(Env::bool($key, true));

            putenv($key . '=maybe');
            self::assertTrue(Env::bool($key, true));
            self::assertFalse(Env::bool($key, false));
        } finally {
            putenv($key);
        }
    }
}
