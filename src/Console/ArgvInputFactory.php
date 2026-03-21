<?php

declare(strict_types=1);

namespace Framework\Console;

/**
 * Превращает raw argv в детерминированный CLI input snapshot.
 */
final class ArgvInputFactory
{
    /**
     * @param array<int, string> $argv
     */
    public function fromArgv(array $argv): CommandInput
    {
        $tokens = array_values($argv);
        array_shift($tokens);

        if ($tokens === []) {
            return new CommandInput(null, [], [], []);
        }

        $commandName = array_shift($tokens);
        $arguments = [];
        $options = [];
        $parsingOptions = true;

        foreach ($tokens as $token) {
            if ($parsingOptions && $token === '--') {
                $parsingOptions = false;
                continue;
            }

            if ($parsingOptions && str_starts_with($token, '--')) {
                $option = substr($token, 2);

                if ($option !== '' && str_contains($option, '=')) {
                    /** @var array{0: string, 1: string} $parts */
                    $parts = explode('=', $option, 2);
                    $name = $parts[0];

                    if ($name !== '') {
                        $options[$name] = $parts[1];
                        continue;
                    }
                }

                if ($option !== '') {
                    $options[$option] = true;
                    continue;
                }
            }

            $arguments[] = $token;
        }

        return new CommandInput(
            $commandName,
            $arguments,
            $options,
            $argv === [] ? [] : array_slice($argv, 1)
        );
    }
}
