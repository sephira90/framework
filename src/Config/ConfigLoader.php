<?php

declare(strict_types=1);

namespace Framework\Config;

/**
 * Загружает один PHP config file и превращает его в Config repository.
 *
 * На уровне v0 loader специально узкий: он не делает merge нескольких файлов,
 * не применяет profile-specific overrides и не содержит собственной логики env.
 */
final class ConfigLoader
{
    /**
     * Требует, чтобы файл существовал и возвращал массив конфигурации.
     */
    public static function load(string $path): Config
    {
        if (!is_file($path)) {
            throw new InvalidConfigurationException(sprintf('Configuration file [%s] was not found.', $path));
        }

        $config = self::requireFile($path);

        if (!is_array($config)) {
            throw new InvalidConfigurationException(sprintf('Configuration file [%s] must return an array.', $path));
        }

        /** @var array<string, mixed> $config */
        return new Config($config);
    }

    /**
     * Изолирует scope подключаемого файла, чтобы config не получал доступ к
     * локальным переменным метода loader'а.
     */
    private static function requireFile(string $path): mixed
    {
        return (static function (string $path): mixed {
            /** @psalm-suppress UnresolvableInclude */
            return require $path;
        })($path);
    }
}
