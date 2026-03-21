<?php

declare(strict_types=1);

namespace Framework\Routing;

use InvalidArgumentException;

/**
 * Простая коллекция маршрутов в порядке регистрации.
 */
final class RouteCollection
{
    /** @var list<Route> */
    private array $routes = [];

    /**
     * Добавляет маршрут в коллекцию.
     */
    public function add(Route $route): int
    {
        $this->routes[] = $route;

        return array_key_last($this->routes);
    }

    /**
     * Заменяет ранее зарегистрированный маршрут, сохраняя порядок коллекции.
     */
    public function replace(int $index, Route $route): void
    {
        if (!array_key_exists($index, $this->routes)) {
            throw new InvalidArgumentException(sprintf('Route index [%d] is not registered.', $index));
        }

        $this->routes[$index] = $route;
    }

    /**
     * Возвращает все зарегистрированные маршруты.
     *
     * @return list<Route>
     */
    public function all(): array
    {
        return $this->routes;
    }
}
