<?php

declare(strict_types=1);

namespace Framework\Foundation\Bootstrap;

use Framework\Config\Config;
use Framework\Config\InvalidConfigurationException;

/**
 * Резолвит project-local bootstrap files из config и не даёт path escape за
 * пределы base path приложения.
 */
final class ProjectFileResolver
{
    /** @psalm-suppress UnusedConstructor */
    private function __construct()
    {
    }

    public static function resolveConfiguredFile(
        Config $config,
        string $configKey,
        string $defaultRelativePath,
        string $basePath,
        string $label,
    ): string {
        return self::resolveRelativeFile(
            $config->get($configKey, $defaultRelativePath),
            $configKey,
            $basePath,
            $label
        );
    }

    public static function resolveRelativeFile(
        mixed $relativePath,
        string $configKey,
        string $basePath,
        string $label,
    ): string {
        if (!is_string($relativePath) || $relativePath === '' || self::isAbsolutePath($relativePath)) {
            throw new InvalidConfigurationException(sprintf(
                'Configuration key [%s] must be a non-empty relative project file path.',
                $configKey
            ));
        }

        $baseRealPath = realpath($basePath);

        if ($baseRealPath === false || !is_dir($baseRealPath)) {
            throw new InvalidConfigurationException(sprintf('Base path [%s] does not exist.', $basePath));
        }

        $normalizedRelativePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
        $candidatePath = $baseRealPath . DIRECTORY_SEPARATOR . ltrim($normalizedRelativePath, DIRECTORY_SEPARATOR);
        $resolvedPath = realpath($candidatePath);

        if ($resolvedPath === false || !is_file($resolvedPath)) {
            throw new InvalidConfigurationException(sprintf('%s [%s] was not found.', $label, $candidatePath));
        }

        if (!self::isPathWithinBasePath($resolvedPath, $baseRealPath)) {
            throw new InvalidConfigurationException(sprintf(
                '%s [%s] must stay within project base path.',
                $label,
                $candidatePath
            ));
        }

        return $resolvedPath;
    }

    private static function isAbsolutePath(string $path): bool
    {
        return preg_match('/^(?:[A-Za-z]:[\\\\\/]|\\\\\\\\|\/)/', $path) === 1;
    }

    private static function isPathWithinBasePath(string $path, string $basePath): bool
    {
        $normalizedPath = self::normalizePathForComparison($path);
        $normalizedBasePath = rtrim(self::normalizePathForComparison($basePath), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR;

        return str_starts_with($normalizedPath, $normalizedBasePath);
    }

    private static function normalizePathForComparison(string $path): string
    {
        $normalizedPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        if (PHP_OS_FAMILY === 'Windows') {
            return strtolower($normalizedPath);
        }

        return $normalizedPath;
    }
}
