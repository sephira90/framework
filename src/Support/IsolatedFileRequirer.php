<?php

declare(strict_types=1);

namespace Framework\Support;

/**
 * Подключает PHP-файл в изолированном scope, чтобы не протекало локальное
 * состояние вызывающего метода.
 */
final class IsolatedFileRequirer
{
    /** @psalm-suppress UnusedConstructor */
    private function __construct()
    {
    }

    public static function require(string $path): mixed
    {
        return (static function (string $path): mixed {
            /** @psalm-suppress UnresolvableInclude */
            return require $path;
        })($path);
    }
}
