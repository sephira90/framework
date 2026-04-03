<?php

declare(strict_types=1);

namespace Framework\Console\Internal;

use Closure;
use Framework\Config\ProjectConfigLoader;
use Framework\Console\CommandInput;
use Framework\Console\CommandInterface;
use Framework\Console\ConsoleOutput;
use Framework\Foundation\Bootstrap\ConfiguredContainerConfig;
use JsonException;
use Override;

/**
 * Prints either the runtime or the source configuration view as deterministic
 * JSON for inspection and debugging.
 */
final readonly class ConfigShowCommand implements CommandInterface
{
    public function __construct(
        private string $basePath,
        private ProjectConfigLoader $configLoader = new ProjectConfigLoader(),
    ) {
    }

    #[Override]
    public function execute(CommandInput $input, ConsoleOutput $output): int
    {
        $config = $input->option('source', false) === true
            ? $this->configLoader->loadSource($this->basePath)
            : $this->configLoader->loadRuntime($this->basePath);

        $path = $input->argument(0);

        if ($path !== null && !$config->has($path)) {
            $output->writeErrorLine(sprintf('Config path [%s] was not found.', $path));
            return 1;
        }

        /** @psalm-suppress MixedAssignment */
        $value = $path === null ? $config->all() : $config->get($path);

        $output->writeln($this->encodeJson($this->normalizeValue($value)));

        return 0;
    }

    private function encodeJson(mixed $value): string
    {
        try {
            return json_encode(
                $value,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new \RuntimeException('Unable to encode config view as JSON.', 0, $exception);
        }
    }

    private function normalizeValue(mixed $value): mixed
    {
        if (is_null($value) || is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
            return $value;
        }

        if (is_array($value)) {
            $normalized = [];

            /** @psalm-suppress MixedAssignment */
            foreach ($value as $key => $item) {
                /** @psalm-suppress MixedAssignment */
                $normalized[$key] = $this->normalizeValue($item);
            }

            return $normalized;
        }

        if ($value instanceof ConfiguredContainerConfig) {
            return [
                '_type' => ConfiguredContainerConfig::class,
                'bindings' => $this->normalizeValue($value->bindings()),
                'singletons' => $this->normalizeValue($value->singletons()),
                'aliases' => $this->normalizeValue($value->aliases()),
            ];
        }

        if ($value instanceof Closure) {
            return [
                '_type' => Closure::class,
            ];
        }

        if (is_object($value)) {
            return [
                '_type' => 'object',
                'class' => $value::class,
            ];
        }

        return [
            '_type' => gettype($value),
        ];
    }
}
