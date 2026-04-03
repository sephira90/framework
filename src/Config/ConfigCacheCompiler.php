<?php

declare(strict_types=1);

namespace Framework\Config;

use Framework\Foundation\Bootstrap\ConfiguredContainerConfigValidator;
use Framework\Foundation\Bootstrap\FrameworkCacheMetadata;
use Framework\Foundation\Bootstrap\FrameworkCachePaths;

/**
 * Builds an explicit config cache snapshot from source config files.
 */
final readonly class ConfigCacheCompiler
{
    public function __construct(
        private ConfiguredContainerConfigValidator $containerConfigValidator = new ConfiguredContainerConfigValidator(),
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function compile(Config $config): array
    {
        $items = $config->all();

        /** @psalm-suppress LiteralKeyUnshapedArray */
        if (array_key_exists('_framework', $items)) {
            throw new InvalidConfigurationException(
                'Configuration root key [_framework] is reserved for framework cache metadata.'
            );
        }

        $exportableItems = $this->assertExportableArray($items, 'config');
        $exportableItems['_framework'] = [
            'cache' => FrameworkCacheMetadata::forType(FrameworkCacheMetadata::CONFIG_TYPE),
            'container_config' => $this->containerConfigValidator->validate($config->get('container', [])),
        ];

        /** @var array<string, mixed> $exportableItems */
        return $exportableItems;
    }

    public function render(Config $config): string
    {
        return "<?php\n\ndeclare(strict_types=1);\n\nreturn "
            . var_export($this->compile($config), true)
            . ";\n";
    }

    public function cacheFile(string $basePath): string
    {
        return (new FrameworkCachePaths($basePath))->configFile();
    }

    /**
     * @param array<array-key, mixed> $value
     * @return array<array-key, mixed>
     */
    private function assertExportableArray(array $value, string $path): array
    {
        $exportable = [];

        /** @psalm-suppress MixedAssignment */
        foreach ($value as $key => $item) {
            $segment = is_int($key) ? sprintf('%s[%d]', $path, $key) : sprintf('%s.%s', $path, $key);
            $exportable[$key] = $this->assertExportableValue($item, $segment);
        }

        return $exportable;
    }

    private function assertExportableValue(mixed $value, string $path): mixed
    {
        if (is_null($value) || is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
            return $value;
        }

        if (!is_array($value)) {
            throw new InvalidConfigurationException(sprintf(
                'Config cache cannot export [%s] because it contains a non-exportable [%s] value.',
                $path,
                is_object($value) ? $value::class : gettype($value)
            ));
        }

        return $this->assertExportableArray($value, $path);
    }
}
