<?php

declare(strict_types=1);

namespace Framework\Foundation\Bootstrap;

use Framework\Routing\RouteCollection;

/**
 * Single-assignment holder for routes loaded during bootstrap.
 */
final class RouteRegistry
{
    private ?RouteCollection $routes = null;

    public function initialize(RouteCollection $routes): void
    {
        if ($this->routes !== null) {
            throw new BootstrapStateException('Route registry has already been initialized.');
        }

        $this->routes = $routes;
    }

    public function routes(): RouteCollection
    {
        if ($this->routes === null) {
            throw new BootstrapStateException('Route registry has not been initialized yet.');
        }

        return $this->routes;
    }

    public function isInitialized(): bool
    {
        return $this->routes !== null;
    }
}
