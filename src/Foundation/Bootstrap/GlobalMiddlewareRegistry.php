<?php

declare(strict_types=1);

namespace Framework\Foundation\Bootstrap;

use Psr\Http\Server\MiddlewareInterface;

/**
 * Single-assignment holder for global middleware definitions.
 */
final class GlobalMiddlewareRegistry
{
    /** @var SingleAssignmentHolder<list<class-string<MiddlewareInterface>|MiddlewareInterface>> */
    private SingleAssignmentHolder $middleware;

    public function __construct()
    {
        /** @var SingleAssignmentHolder<list<class-string<MiddlewareInterface>|MiddlewareInterface>> $middleware */
        $middleware = new SingleAssignmentHolder('Global middleware registry');

        $this->middleware = $middleware;
    }

    /**
     * @param list<class-string<MiddlewareInterface>|MiddlewareInterface> $middleware
     */
    public function initialize(array $middleware): void
    {
        $this->middleware->initialize($middleware);
    }

    /**
     * @return list<class-string<MiddlewareInterface>|MiddlewareInterface>
     */
    public function middleware(): array
    {
        $middleware = $this->middleware->get();

        return $middleware;
    }

    public function isInitialized(): bool
    {
        return $this->middleware->isInitialized();
    }
}
