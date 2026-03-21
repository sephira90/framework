<?php

declare(strict_types=1);

namespace Framework\Routing;

/**
 * Fluent post-registration API для опциональных route metadata.
 *
 * Builder не строит маршрут с нуля. Он держит ссылку на уже зарегистрированный
 * immutable Route и при дополнительных вызовах заменяет его обновлённой версией
 * в той же позиции коллекции.
 */
final class RouteBuilder
{
    public function __construct(
        private readonly RouteCollection $routes,
        private Route $route,
        private readonly int $index,
    ) {
    }

    /**
     * Присваивает маршруту имя для URL generation.
     */
    public function name(string $name): self
    {
        $this->route = $this->route->withName($name);
        $this->routes->replace($this->index, $this->route);

        return $this;
    }
}
