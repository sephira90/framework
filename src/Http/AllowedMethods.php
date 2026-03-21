<?php

declare(strict_types=1);

namespace Framework\Http;

/**
 * Канонизирует HTTP methods для surface'ов, которые строят `405 Allow`.
 */
final class AllowedMethods
{
    /** @psalm-suppress UnusedConstructor */
    private function __construct()
    {
    }

    /**
     * @param list<string> $methods
     * @return list<string>
     */
    public static function normalize(array $methods): array
    {
        $normalizedMethods = array_values(array_unique(array_map(
            static fn (string $method): string => strtoupper($method),
            $methods
        )));

        if (in_array('GET', $normalizedMethods, true) && !in_array('HEAD', $normalizedMethods, true)) {
            $normalizedMethods[] = 'HEAD';
        }

        sort($normalizedMethods);

        return $normalizedMethods;
    }
}
