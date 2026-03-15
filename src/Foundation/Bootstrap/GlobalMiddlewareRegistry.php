<?php

declare(strict_types=1);

namespace Framework\Foundation\Bootstrap;

use Psr\Http\Server\MiddlewareInterface;

/**
 * Single-assignment holder for global middleware definitions.
 */
final class GlobalMiddlewareRegistry
{
    /** @var list<class-string<MiddlewareInterface>|MiddlewareInterface>|null */
    private ?array $middleware = null;

    /**
     * @param list<class-string<MiddlewareInterface>|MiddlewareInterface> $middleware
     */
    public function initialize(array $middleware): void
    {
        if ($this->middleware !== null) {
            throw new BootstrapStateException('Global middleware registry has already been initialized.');
        }

        $this->middleware = $middleware;
    }

    /**
     * @return list<class-string<MiddlewareInterface>|MiddlewareInterface>
     */
    public function middleware(): array
    {
        if ($this->middleware === null) {
            throw new BootstrapStateException('Global middleware registry has not been initialized yet.');
        }

        return $this->middleware;
    }

    public function isInitialized(): bool
    {
        return $this->middleware !== null;
    }
}
