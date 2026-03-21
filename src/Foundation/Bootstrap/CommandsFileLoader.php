<?php

declare(strict_types=1);

namespace Framework\Foundation\Bootstrap;

use Framework\Config\Config;
use Framework\Config\InvalidConfigurationException;
use Framework\Console\CommandCollection;
use Framework\Console\CommandCollector;

/**
 * Загружает command collection из configured commands file.
 */
final class CommandsFileLoader
{
    public function load(string $basePath, Config $config): CommandCollection
    {
        $relativeCommandsPath = $config->get('console.commands', 'commands/console.php');

        if (!is_string($relativeCommandsPath) || $relativeCommandsPath === '') {
            throw new InvalidConfigurationException('Configuration key [console.commands] must be a non-empty string.');
        }

        $commandsPath = $basePath
            . DIRECTORY_SEPARATOR
            . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativeCommandsPath);

        if (!is_file($commandsPath)) {
            throw new InvalidConfigurationException(sprintf('Commands file [%s] was not found.', $commandsPath));
        }

        $registrar = $this->requireFile($commandsPath);

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

    /**
     * Изолирует scope commands file от локального состояния loader'а.
     */
    private function requireFile(string $path): mixed
    {
        return (static function (string $path): mixed {
            /** @psalm-suppress UnresolvableInclude */
            return require $path;
        })($path);
    }
}
