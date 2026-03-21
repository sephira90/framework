<?php

declare(strict_types=1);

namespace Framework\Console;

use Framework\Config\InvalidConfigurationException;

/**
 * Упорядоченная коллекция зарегистрированных console commands.
 */
final class CommandCollection
{
    /** @var array<string, class-string<CommandInterface>> */
    private array $commands = [];

    /**
     * Принимает raw class-string с границы commands file и валидирует его
     * в runtime, а не через доверие к одному только PHPDoc.
     */
    public function add(string $name, string $handler): void
    {
        $normalizedName = trim($name);

        if ($normalizedName === '') {
            throw new InvalidConfigurationException('Command name must be a non-empty string.');
        }

        if (!class_exists($handler)) {
            throw new InvalidConfigurationException(sprintf(
                'Command [%s] references unknown class [%s].',
                $normalizedName,
                $handler
            ));
        }

        if (!is_a($handler, CommandInterface::class, true)) {
            throw new InvalidConfigurationException(sprintf(
                'Command [%s] must reference a class implementing %s.',
                $normalizedName,
                CommandInterface::class
            ));
        }

        if (array_key_exists($normalizedName, $this->commands)) {
            throw new InvalidConfigurationException(sprintf(
                'Command [%s] is already registered.',
                $normalizedName
            ));
        }

        $this->commands[$normalizedName] = $handler;
    }

    /**
     * @return array<string, class-string<CommandInterface>>
     */
    public function all(): array
    {
        return $this->commands;
    }
}
