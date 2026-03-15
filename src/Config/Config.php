<?php

declare(strict_types=1);

namespace Framework\Config;

/**
 * Immutable repository поверх уже загруженной конфигурации приложения.
 *
 * Класс не знает, откуда взялись значения и как они были собраны. Его задача
 * уже после bootstrap предоставить детерминированный dotted access без
 * скрытых побочных эффектов.
 */
final readonly class Config
{
    /**
     * @param array<string, mixed> $items
     */
    public function __construct(
        private array $items,
    ) {
    }

    /**
     * Возвращает весь конфиг как есть.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Проверяет существование пути вида `section.key.nested`.
     */
    public function has(string $key): bool
    {
        return $key === '' ? true : $this->pathExists($this->items, explode('.', $key));
    }

    /**
     * Возвращает значение по dotted path или default, если путь не существует.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if ($key === '') {
            return $this->items;
        }

        return $this->resolvePath($this->items, explode('.', $key), $default);
    }

    /**
     * @param array<string, mixed> $items
     * @param list<string> $segments
     */
    private function pathExists(array $items, array $segments): bool
    {
        if ($segments === []) {
            return true;
        }

        $segment = $segments[0];
        $remaining = array_slice($segments, 1);

        if (!array_key_exists($segment, $items)) {
            return false;
        }

        if ($remaining === []) {
            return true;
        }

        $next = $items[$segment];

        if (!is_array($next)) {
            return false;
        }

        /** @var array<string, mixed> $next */
        return $this->pathExists($next, $remaining);
    }

    /**
     * @param array<string, mixed> $items
     * @param list<string> $segments
     */
    private function resolvePath(array $items, array $segments, mixed $default): mixed
    {
        if ($segments === []) {
            return $items;
        }

        $segment = $segments[0];
        $remaining = array_slice($segments, 1);

        if (!array_key_exists($segment, $items)) {
            return $default;
        }

        $value = $items[$segment];

        if ($remaining === []) {
            return $value;
        }

        if (!is_array($value)) {
            return $default;
        }

        /** @var array<string, mixed> $value */
        return $this->resolvePath($value, $remaining, $default);
    }
}
