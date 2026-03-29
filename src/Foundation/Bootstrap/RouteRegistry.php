<?php

declare(strict_types=1);

namespace Framework\Foundation\Bootstrap;

use Framework\Routing\RouteIndex;

/**
 * Single-assignment holder for routes loaded during bootstrap.
 */
final class RouteRegistry
{
    /** @var SingleAssignmentHolder<RouteIndex> */
    private SingleAssignmentHolder $routes;

    public function __construct()
    {
        /** @var SingleAssignmentHolder<RouteIndex> $routes */
        $routes = new SingleAssignmentHolder('Route registry');

        $this->routes = $routes;
    }

    public function initialize(RouteIndex $routes): void
    {
        $this->routes->initialize($routes);
    }

    public function routes(): RouteIndex
    {
        $routes = $this->routes->get();

        return $routes;
    }

    public function isInitialized(): bool
    {
        return $this->routes->isInitialized();
    }
}
