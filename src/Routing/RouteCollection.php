<?php

declare(strict_types=1);

namespace Framework\Routing;

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
    public function add(Route $route): void
    {
        $this->routes[] = $route;
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
