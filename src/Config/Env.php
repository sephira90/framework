<?php

declare(strict_types=1);

namespace Framework\Config;

/**
 * Минимальный фасад над окружением процесса.
 *
 * Источники читаются в фиксированном порядке: `$_ENV`, затем `$_SERVER`,
 * затем `getenv()`. Это делает поведение predictable, но оставляет класс
 * статическим и потому сознательно простым, а не максимально гибким.
 */
final class Env
{
    /**
     * Возвращает string-представление переменной окружения или default.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, $_ENV)) {
            $value = $_ENV[$key];
        } elseif (array_key_exists($key, $_SERVER)) {
            $value = $_SERVER[$key];
        } else {
            $value = getenv($key);

            if ($value === false) {
                return $default;
            }
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return $default;
    }

    /**
     * Парсит bool-переменную окружения через ограниченный набор truthy/falsy
     * значений. Неизвестные значения не считаются ошибкой и приводят к default.
     */
    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);

        if ($value === null) {
            return $default;
        }

        return match (strtolower(trim($value))) {
            '1', 'true', 'on', 'yes' => true,
            '0', 'false', 'off', 'no' => false,
            default => $default,
        };
    }
}
