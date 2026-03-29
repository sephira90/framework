<?php

declare(strict_types=1);

namespace Framework\Console\Internal;

use Framework\Console\CommandInput;
use Framework\Console\CommandInterface;
use Framework\Console\ConsoleOutput;
use Framework\Foundation\Bootstrap\FrameworkCachePaths;
use Override;

/**
 * Removes all framework-managed cache files.
 */
final readonly class CacheClearCommand implements CommandInterface
{
    public function __construct(
        private FrameworkCachePaths $cachePaths,
    ) {
    }

    #[Override]
    public function execute(CommandInput $input, ConsoleOutput $output): int
    {
        unset($input);

        $removedFiles = [];

        foreach ($this->cachePaths->files() as $file) {
            if (!is_file($file)) {
                continue;
            }

            if (!unlink($file)) {
                throw new \RuntimeException(sprintf('Unable to remove cache file [%s].', $file));
            }

            $removedFiles[] = $file;
        }

        if ($removedFiles === []) {
            $output->writeln('No framework cache files were present.');
            return 0;
        }

        foreach ($removedFiles as $file) {
            $output->writeln(sprintf('Removed [%s].', $file));
        }

        return 0;
    }
}
