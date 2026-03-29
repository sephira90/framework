<?php

declare(strict_types=1);

namespace Framework\Console\Internal;

use Framework\Config\ConfigCacheCompiler;
use Framework\Config\ProjectConfigLoader;
use Framework\Console\CommandInput;
use Framework\Console\CommandInterface;
use Framework\Console\ConsoleOutput;
use Framework\Foundation\Bootstrap\CacheFileWriter;
use Override;

/**
 * Builds the explicit framework config cache from source config files.
 */
final readonly class ConfigCacheCommand implements CommandInterface
{
    public function __construct(
        private string $basePath,
        private ProjectConfigLoader $configLoader = new ProjectConfigLoader(),
        private ConfigCacheCompiler $compiler = new ConfigCacheCompiler(),
        private CacheFileWriter $writer = new CacheFileWriter(),
    ) {
    }

    #[Override]
    public function execute(CommandInput $input, ConsoleOutput $output): int
    {
        unset($input);

        $config = $this->configLoader->loadSource($this->basePath);
        $cacheFile = $this->compiler->cacheFile($this->basePath);
        $this->writer->write($cacheFile, $this->compiler->render($config));

        $output->writeln(sprintf('Config cache written to [%s].', $cacheFile));

        return 0;
    }
}
