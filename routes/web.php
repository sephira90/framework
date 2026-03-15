<?php

declare(strict_types=1);

use App\Http\Handler\HomeHandler;
use Framework\Routing\RouteCollector;

// Routes file не строит runtime и не содержит middleware machinery. Его
// единственная задача — зарегистрировать маршруты через RouteCollector.
return static function (RouteCollector $routes): void {
    $routes->get('/', HomeHandler::class);
};
