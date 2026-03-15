<?php

declare(strict_types=1);

namespace Framework\Routing;

/**
 * Имена request attributes, которые routing кладёт в matched request.
 */
final class RouteAttributes
{
    public const ROUTE = 'framework.route';
    public const PARAMS = 'framework.route_params';
}
