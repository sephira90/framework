<?php

declare(strict_types=1);

namespace Framework\Routing;

/**
 * Набор возможных исходов матчинга маршрута.
 */
enum RouteMatchStatus
{
    case Found;
    case NotFound;
    case MethodNotAllowed;
}
