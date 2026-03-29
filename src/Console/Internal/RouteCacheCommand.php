<?php

declare(strict_types=1);

namespace Framework\Console\Internal;

use Framework\Config\ProjectConfigLoader;
use Framework\Console\CommandInput;
use Framework\Console\CommandInterface;
use Framework\Console\ConsoleOutput;
use Framework\Foundation\Bootstrap\CacheFileWriter;
use Framework\Foundation\Bootstrap\RoutesFileLoader;
use Framework\Routing\RouteCacheCompiler;
use Override;

/**
 * Builds the explicit framework route cache from source route registration.
 */
final readonly class RouteCacheCommand implements CommandInterface
{
    public function __construct(
        private string $basePath,
        private ProjectConfigLoader $configLoader = new ProjectConfigLoader(),
        private RoutesFileLoader $routesLoader = new RoutesFileLoader(),
        private RouteCacheCompiler $compiler = new RouteCacheCompiler(),
        private CacheFileWriter $writer = new CacheFileWriter(),
    ) {
    }

    #[Override]
    public function execute(CommandInput $input, ConsoleOutput $output): int
    {
        unset($input);

        $config = $this->configLoader->loadSource($this->basePath);
        $routes = $this->routesLoader->loadSource($this->basePath, $config);
        $cacheFile = $this->compiler->cacheFile($this->basePath);
        $this->writer->write($cacheFile, $this->compiler->render($routes));

        $output->writeln(sprintf('Route cache written to [%s].', $cacheFile));

        return 0;
    }
}
