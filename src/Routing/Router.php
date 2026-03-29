<?php

declare(strict_types=1);

namespace Framework\Routing;

use Framework\Routing\Exception\UrlGenerationException;

/**
 * Thin facade over the precompiled route index.
 */
final readonly class Router
{
    private RouteIndex $index;

    public function __construct(
        RouteCollection|RouteIndex $index,
    ) {
        $this->index = $index instanceof RouteIndex ? $index : RouteIndex::fromRouteCollection($index);
    }

    /**
     * Ищет маршрут по HTTP method и path.
     */
    public function match(string $method, string $path): RouteMatch
    {
        return $this->index->match($method, $path);
    }

    /**
     * Генерирует path по имени маршрута и набору route parameters.
     *
     * @param array<string, string|int|float> $parameters
     *
     * @throws UrlGenerationException
     */
    public function url(string $name, array $parameters = []): string
    {
        return $this->index->url($name, $parameters);
    }
}
