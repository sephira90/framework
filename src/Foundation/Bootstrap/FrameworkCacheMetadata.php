<?php

declare(strict_types=1);

namespace Framework\Foundation\Bootstrap;

use Framework\Config\InvalidConfigurationException;

/**
 * Defines and validates framework-managed cache payload metadata.
 */
final class FrameworkCacheMetadata
{
    public const VERSION = 1;
    public const CONFIG_TYPE = 'config';
    public const ROUTES_TYPE = 'routes';

    /**
     * @return array{type: non-empty-string, version: positive-int}
     */
    public static function forType(string $type): array
    {
        if ($type === '') {
            throw new InvalidConfigurationException('Framework cache type must not be empty.');
        }

        return [
            'type' => $type,
            'version' => self::VERSION,
        ];
    }

    public static function assertValid(mixed $metadata, string $expectedType, string $path): void
    {
        if (!is_array($metadata)) {
            throw new InvalidConfigurationException(sprintf(
                'Cache file [%s] must define framework cache metadata.',
                $path
            ));
        }

        /** @var array{type?: mixed, version?: mixed} $metadata */
        $type = $metadata['type'] ?? null;
        $version = $metadata['version'] ?? null;

        if (!is_string($type) || $type === '') {
            throw new InvalidConfigurationException(sprintf(
                'Cache file [%s] must define a non-empty cache type.',
                $path
            ));
        }

        if ($type !== $expectedType) {
            throw new InvalidConfigurationException(sprintf(
                'Cache file [%s] has incompatible cache type [%s]; expected [%s].',
                $path,
                $type,
                $expectedType
            ));
        }

        if (!is_int($version)) {
            throw new InvalidConfigurationException(sprintf(
                'Cache file [%s] must define an integer cache version.',
                $path
            ));
        }

        if ($version !== self::VERSION) {
            throw new InvalidConfigurationException(sprintf(
                'Cache file [%s] has unsupported cache version [%d]; expected [%d].',
                $path,
                $version,
                self::VERSION
            ));
        }
    }
}
