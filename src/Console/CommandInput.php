<?php

declare(strict_types=1);

namespace Framework\Console;

/**
 * Immutable snapshot распарсенного CLI input.
 */
final readonly class CommandInput
{
    /**
     * @param list<string> $arguments
     * @param array<string, string|bool> $options
     * @param list<string> $rawTokens
     */
    public function __construct(
        private ?string $commandName,
        private array $arguments,
        private array $options,
        private array $rawTokens,
    ) {
    }

    public function commandName(): ?string
    {
        return $this->commandName;
    }

    /**
     * @return list<string>
     */
    public function arguments(): array
    {
        return $this->arguments;
    }

    public function argument(int $index, ?string $default = null): ?string
    {
        return $this->arguments[$index] ?? $default;
    }

    /**
     * @return array<string, string|bool>
     */
    public function options(): array
    {
        return $this->options;
    }

    public function option(string $name, string|bool|null $default = null): string|bool|null
    {
        return $this->options[$name] ?? $default;
    }

    /**
     * @return list<string>
     */
    public function rawTokens(): array
    {
        return $this->rawTokens;
    }
}
