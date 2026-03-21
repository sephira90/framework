<?php

declare(strict_types=1);

namespace Framework\Console;

use Framework\Console\Exception\InvalidCommandException;
use Psr\Container\ContainerInterface;

/**
 * Резолвит command class через контейнер и валидирует runtime contract.
 */
final readonly class CommandResolver
{
    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    /**
     * @param class-string<CommandInterface> $commandClass
     */
    public function resolve(string $commandClass): CommandInterface
    {
        $command = $this->container->get($commandClass);

        if (!$command instanceof CommandInterface) {
            throw new InvalidCommandException(sprintf(
                'Resolved command [%s] must implement %s.',
                $commandClass,
                CommandInterface::class
            ));
        }

        return $command;
    }
}
