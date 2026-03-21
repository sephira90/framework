<?php

declare(strict_types=1);

namespace Framework\Config;

use Framework\Support\IsolatedFileRequirer;

/**
 * Загружает конфигурацию приложения из одного файла или из config directory.
 *
 * Directory mode нужен для post-v0 конфигурационной модели:
 *
 * - базовые `.php` файлы в каталоге конфигурации мержатся детерминированно;
 * - optional environment overlay из `config/environments/<env>.php` применяется
 *   после базовой сборки;
 * - env выбирается из уже собранного `app.env`, поэтому `.env` должен быть
 *   загружен раньше bootstrap'ом.
 */
final class ConfigLoader
{
    /**
     * Требует, чтобы target существовал и возвращал валидную конфигурацию.
     */
    public static function load(string $path): Config
    {
        if (is_dir($path)) {
            return new Config(self::loadDirectory($path));
        }

        if (!is_file($path)) {
            throw new InvalidConfigurationException(sprintf('Configuration file [%s] was not found.', $path));
        }

        return new Config(self::loadFile($path));
    }

    /**
     * Загружает все `.php` файлы из config directory и затем optional environment overlay.
     *
     * @return array<string, mixed>
     */
    private static function loadDirectory(string $path): array
    {
        $files = self::collectPhpFiles($path);

        if ($files === []) {
            throw new InvalidConfigurationException(sprintf(
                'Configuration directory [%s] does not contain any PHP config files.',
                $path
            ));
        }

        $config = [];

        foreach ($files as $file) {
            $config = self::mergeConfig($config, self::loadFile($file));
        }

        $environment = self::resolveEnvironment($config);

        if ($environment === null) {
            return $config;
        }

        $environmentFile = $path
            . DIRECTORY_SEPARATOR
            . 'environments'
            . DIRECTORY_SEPARATOR
            . $environment
            . '.php';

        if (!is_file($environmentFile)) {
            return $config;
        }

        return self::mergeConfig($config, self::loadFile($environmentFile));
    }

    /**
     * Требует, чтобы файл существовал и возвращал массив конфигурации.
     *
     * @return array<string, mixed>
     */
    private static function loadFile(string $path): array
    {
        $config = IsolatedFileRequirer::require($path);

        if (!is_array($config)) {
            throw new InvalidConfigurationException(sprintf('Configuration file [%s] must return an array.', $path));
        }

        return self::normalizeRootArray($config, $path);
    }

    /**
     * @return list<string>
     */
    private static function collectPhpFiles(string $path): array
    {
        $entries = scandir($path);

        if ($entries === false) {
            throw new InvalidConfigurationException(sprintf(
                'Configuration directory [%s] could not be read.',
                $path
            ));
        }

        $files = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $file = $path . DIRECTORY_SEPARATOR . $entry;

            if (!is_file($file) || pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
                continue;
            }

            $files[] = $file;
        }

        sort($files);

        return $files;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function resolveEnvironment(array $config): ?string
    {
        /** @var array{app?: mixed} $config */
        $app = $config['app'] ?? null;

        if (!is_array($app)) {
            return null;
        }

        /** @var array{env?: mixed} $app */
        return isset($app['env']) && is_string($app['env']) && $app['env'] !== ''
            ? $app['env']
            : null;
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $override
     * @return array<string, mixed>
     *
     * @psalm-suppress MixedAssignment
     */
    private static function mergeConfig(array $base, array $override): array
    {
        $merged = $base;

        foreach ($override as $key => $overrideValue) {
            if (
                array_key_exists($key, $merged)
                && is_array($merged[$key])
                && is_array($overrideValue)
                && !array_is_list($merged[$key])
                && !array_is_list($overrideValue)
            ) {
                /** @var array<string, mixed> $currentValue */
                $currentValue = $merged[$key];
                /** @var array<string, mixed> $overrideValue */
                $merged[$key] = self::mergeConfig($currentValue, $overrideValue);
                continue;
            }

            $merged[$key] = $overrideValue;
        }

        return $merged;
    }

    /**
     * @param array<array-key, mixed> $config
     * @return array<string, mixed>
     *
     * @psalm-suppress MixedAssignment
     */
    private static function normalizeRootArray(array $config, string $path): array
    {
        $normalized = [];

        foreach ($config as $key => $value) {
            if (!is_string($key)) {
                throw new InvalidConfigurationException(sprintf(
                    'Configuration file [%s] must use string keys at the root level.',
                    $path
                ));
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }
}
