<?php

declare(strict_types=1);

namespace Framework\Foundation\Bootstrap;

use Framework\Routing\RouteCollection;

/**
 * Single-assignment holder for routes loaded during bootstrap.
 */
final class RouteRegistry
{
    /** @var SingleAssignmentHolder<RouteCollection> */
    private SingleAssignmentHolder $routes;

    public function __construct()
    {
        /** @var SingleAssignmentHolder<RouteCollection> $routes */
        $routes = new SingleAssignmentHolder('Route registry');

        $this->routes = $routes;
    }

    public function initialize(RouteCollection $routes): void
    {
        $this->routes->initialize($routes);
    }

    public function routes(): RouteCollection
    {
        $routes = $this->routes->get();

        return $routes;
    }

    public function isInitialized(): bool
    {
        return $this->routes->isInitialized();
    }
}
