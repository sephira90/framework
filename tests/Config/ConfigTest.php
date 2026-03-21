<?php

declare(strict_types=1);

namespace Framework\Tests\Config;

use Framework\Config\Config;
use Framework\Config\ConfigLoader;
use Framework\Config\Env;
use Framework\Config\EnvironmentLoader;
use Framework\Tests\Support\FrameworkTestCase;

/** @psalm-suppress UnusedClass */
final class ConfigTest extends FrameworkTestCase
{
    public function testConfigExposesNestedValuesAndPresenceChecks(): void
    {
        $items = [
            'app' => [
                'name' => 'Framework',
                'debug' => true,
            ],
            'paths' => [
                'routes' => 'routes/web.php',
            ],
        ];

        $config = new Config($items);

        self::assertSame($items, $config->all());
        self::assertSame($items, $config->get(''));
        self::assertTrue($config->has(''));
        self::assertTrue($config->has('app.name'));
        self::assertFalse($config->has('app.locale'));
        self::assertSame('Framework', $config->get('app.name'));
        self::assertTrue($config->get('app.debug'));
        self::assertSame('fallback', $config->get('app.locale', 'fallback'));
    }

    public function testConfigLoaderReadsDeterministicPhpArrayConfiguration(): void
    {
        $basePath = $this->createTempDirectory();

        $this->writeFile($basePath, 'config/app.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'Loaded Framework',
        'env' => 'testing',
    ],
];
PHP);

        $config = ConfigLoader::load($basePath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php');

        self::assertSame('Loaded Framework', $config->get('app.name'));
        self::assertSame('testing', $config->get('app.env'));
    }

    public function testConfigLoaderMergesConfigurationDirectoryFiles(): void
    {
        $basePath = $this->createTempDirectory();

        $this->writeFile($basePath, 'config/app.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'Framework',
        'env' => 'testing',
        'debug' => false,
    ],
];
PHP);
        $this->writeFile($basePath, 'config/container.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    'container' => [
        'bindings' => [],
        'singletons' => [
            'service_one' => 'value',
        ],
        'aliases' => [],
    ],
];
PHP);
        $this->writeFile($basePath, 'config/http.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    'routes' => 'routes/web.php',
    'middleware' => [],
];
PHP);

        $config = ConfigLoader::load($basePath . DIRECTORY_SEPARATOR . 'config');

        self::assertSame('Framework', $config->get('app.name'));
        self::assertSame('testing', $config->get('app.env'));
        self::assertSame('routes/web.php', $config->get('routes'));
        self::assertSame('value', $config->get('container.singletons.service_one'));
    }

    public function testConfigLoaderAppliesEnvironmentOverlayAfterBaseMerge(): void
    {
        $basePath = $this->createTempDirectory();

        $this->writeFile($basePath, 'config/app.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'Framework',
        'env' => 'production',
        'debug' => false,
    ],
];
PHP);
        $this->writeFile($basePath, 'config/http.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    'middleware' => [
        'base-middleware',
    ],
];
PHP);
        $this->writeFile($basePath, 'config/environments/production.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    'app' => [
        'debug' => true,
    ],
    'middleware' => [
        'production-middleware',
    ],
];
PHP);

        $config = ConfigLoader::load($basePath . DIRECTORY_SEPARATOR . 'config');

        self::assertTrue($config->get('app.debug'));
        self::assertSame(['production-middleware'], $config->get('middleware'));
    }

    public function testEnvironmentLoaderPopulatesEnvBeforeConfigurationIsRead(): void
    {
        $basePath = $this->createTempDirectory();
        $appNameKey = 'FRAMEWORK_TEST_APP_NAME_' . bin2hex(random_bytes(4));
        $debugKey = 'FRAMEWORK_TEST_APP_DEBUG_' . bin2hex(random_bytes(4));
        $loader = new EnvironmentLoader();

        $this->writeFile($basePath, '.env', sprintf("%s=\"Runtime Framework\"\n%s=true\n", $appNameKey, $debugKey));
        $this->writeFile($basePath, 'config/app.php', <<<PHP
<?php

declare(strict_types=1);

use Framework\Config\Env;

return [
    'app' => [
        'name' => Env::get('{$appNameKey}', 'fallback'),
        'debug' => Env::bool('{$debugKey}', false),
    ],
];
PHP);

        try {
            $loader->load($basePath);
            $config = ConfigLoader::load($basePath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php');

            self::assertSame('Runtime Framework', $config->get('app.name'));
            self::assertTrue($config->get('app.debug'));
        } finally {
            $this->clearEnvironmentValue($appNameKey);
            $this->clearEnvironmentValue($debugKey);
        }
    }

    public function testEnvironmentLoaderCanReloadSamePathAfterEnvironmentIsCleared(): void
    {
        $basePath = $this->createTempDirectory();
        $key = 'FRAMEWORK_TEST_RELOAD_' . bin2hex(random_bytes(4));
        $loader = new EnvironmentLoader();

        try {
            $this->writeFile($basePath, '.env', $key . "=first\n");
            $loader->load($basePath);

            self::assertSame('first', Env::get($key));

            $this->clearEnvironmentValue($key);
            $this->writeFile($basePath, '.env', $key . "=second\n");
            $loader->load($basePath);

            self::assertSame('second', Env::get($key));
        } finally {
            $this->clearEnvironmentValue($key);
        }
    }

    /**
     * @param non-falsy-string $key
     */
    private function clearEnvironmentValue(string $key): void
    {
        unset($_ENV[$key], $_SERVER[$key]);
        putenv($key);
    }
}
