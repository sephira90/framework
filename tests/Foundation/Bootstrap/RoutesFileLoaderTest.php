<?php

declare(strict_types=1);

namespace Framework\Tests\Foundation\Bootstrap;

use Framework\Config\Config;
use Framework\Config\InvalidConfigurationException;
use Framework\Foundation\Bootstrap\RoutesFileLoader;
use Framework\Routing\RouteMatchStatus;
use Framework\Routing\Router;
use Framework\Tests\Support\FrameworkTestCase;

/** @psalm-suppress UnusedClass */
final class RoutesFileLoaderTest extends FrameworkTestCase
{
    public function testRoutesFileLoaderLoadsConfiguredProjectFile(): void
    {
        $basePath = $this->createTempDirectory();
        $this->writeFile($basePath, 'routes/web.php', <<<'PHP'
<?php

declare(strict_types=1);

use Framework\Routing\RouteCollector;

return static function (RouteCollector $routes): void {
    $routes->get('/hello', 'hello-handler');
};
PHP);

        $collection = (new RoutesFileLoader())->load($basePath, new Config([
            'routes' => 'routes/web.php',
        ]));

        $router = new Router($collection);

        self::assertSame(RouteMatchStatus::Found, $router->match('GET', '/hello')->status());
    }

    public function testRoutesFileLoaderRejectsMissingFile(): void
    {
        $basePath = $this->createTempDirectory();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('was not found');

        (new RoutesFileLoader())->load($basePath, new Config([
            'routes' => 'routes/missing.php',
        ]));
    }

    public function testRoutesFileLoaderRejectsNonCallableRegistrar(): void
    {
        $basePath = $this->createTempDirectory();
        $this->writeFile($basePath, 'routes/web.php', <<<'PHP'
<?php

declare(strict_types=1);

return [];
PHP);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('must return a callable registrar');

        (new RoutesFileLoader())->load($basePath, new Config([
            'routes' => 'routes/web.php',
        ]));
    }

    public function testRoutesFileLoaderRejectsTraversalOutsideBasePath(): void
    {
        $basePath = $this->createTempDirectory();
        $outsidePath = $this->createTempDirectory();
        $this->writeFile($outsidePath, 'escape.php', <<<'PHP'
<?php

declare(strict_types=1);

use Framework\Routing\RouteCollector;

return static function (RouteCollector $routes): void {
    $routes->get('/escape', 'escape-handler');
};
PHP);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('must stay within project base path');

        (new RoutesFileLoader())->load($basePath, new Config([
            'routes' => '..' . DIRECTORY_SEPARATOR . basename($outsidePath) . DIRECTORY_SEPARATOR . 'escape.php',
        ]));
    }
}
