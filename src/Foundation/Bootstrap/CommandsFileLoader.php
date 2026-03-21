<?php

declare(strict_types=1);

namespace Framework\Foundation\Bootstrap;

use Framework\Config\Config;
use Framework\Config\InvalidConfigurationException;
use Framework\Console\CommandCollection;
use Framework\Console\CommandCollector;
use Framework\Support\IsolatedFileRequirer;

/**
 * Загружает command collection из configured commands file.
 */
final class CommandsFileLoader
{
    public function load(string $basePath, Config $config): CommandCollection
    {
        $commandsPath = ProjectFileResolver::resolveConfiguredFile(
            $config,
            'console.commands',
            'commands/console.php',
            $basePath,
            'Commands file'
        );
        $registrar = IsolatedFileRequirer::require($commandsPath);

        if (!is_callable($registrar)) {
            throw new InvalidConfigurationException(sprintf(
                'Commands file [%s] must return a callable registrar.',
                $commandsPath
            ));
        }

        $collector = new CommandCollector(new CommandCollection());
        $registrar($collector);

        return $collector->collection();
    }
}
