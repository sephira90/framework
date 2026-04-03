<?php

declare(strict_types=1);

namespace Framework\Config;

use Framework\Foundation\Bootstrap\ConfiguredContainerConfig;
use Framework\Foundation\Bootstrap\FrameworkCacheMetadata;
use Framework\Foundation\Bootstrap\FrameworkCachePaths;
use Framework\Support\IsolatedFileRequirer;

/**
 * Loads project configuration either from source files or from the explicit
 * framework cache when it already exists.
 */
final readonly class ProjectConfigLoader
{
    public function __construct(
        private EnvironmentLoader $environmentLoader = new EnvironmentLoader(),
    ) {
    }

    public function loadRuntime(string $basePath): Config
    {
        $this->environmentLoader->load($basePath);

        $cachePaths = new FrameworkCachePaths($basePath);

        if (is_file($cachePaths->configFile())) {
            return $this->loadCachedConfig($cachePaths->configFile());
        }

        return $this->loadSource($basePath);
    }

    public function loadSource(string $basePath): Config
    {
        $this->environmentLoader->load($basePath);

        return ConfigLoader::load($basePath . DIRECTORY_SEPARATOR . 'config');
    }

    public function loadRecovery(string $basePath): Config
    {
        return $this->loadSource($basePath);
    }

    private function loadCachedConfig(string $path): Config
    {
        $config = IsolatedFileRequirer::require($path);

        if (!is_array($config)) {
            throw new InvalidConfigurationException(sprintf(
                'Config cache file [%s] must return an array.',
                $path
            ));
        }

        $normalizedConfig = $this->assertRootKeys($config, $path);
        /** @var array{_framework?: mixed} $normalizedConfig */
        $framework = $normalizedConfig['_framework'] ?? null;

        if (!is_array($framework)) {
            throw new InvalidConfigurationException(sprintf(
                'Config cache file [%s] must define [_framework] metadata.',
                $path
            ));
        }

        /** @var array{cache?: mixed, container_config?: mixed} $framework */
        FrameworkCacheMetadata::assertValid(
            $framework['cache'] ?? null,
            FrameworkCacheMetadata::CONFIG_TYPE,
            $path
        );

        if (!($framework['container_config'] ?? null) instanceof ConfiguredContainerConfig) {
            throw new InvalidConfigurationException(sprintf(
                'Config cache file [%s] must contain validated container metadata.',
                $path
            ));
        }

        return new Config($normalizedConfig);
    }

    /**
     * @param array<array-key, mixed> $config
     * @return array<string, mixed>
     */
    private function assertRootKeys(array $config, string $path): array
    {
        $normalized = [];

        /** @psalm-suppress MixedAssignment */
        foreach ($config as $key => $value) {
            if (!is_string($key)) {
                throw new InvalidConfigurationException(sprintf(
                    'Config cache file [%s] must use string keys at the root level.',
                    $path
                ));
            }

            /** @psalm-suppress MixedAssignment */
            $normalized[$key] = $value;
        }

        return $normalized;
    }
}
