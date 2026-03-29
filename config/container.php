<?php

declare(strict_types=1);

use App\Console\Command\AboutCommand;
use App\Http\Handler\HomeHandler;
use App\Support\AppServiceFactory;

// Container slice живёт отдельно, чтобы сервисные bindings не смешивались с
// application metadata и HTTP settings.
return [
    'container' => [
        'bindings' => [],
        'singletons' => [
            AboutCommand::class => [AppServiceFactory::class, 'makeAboutCommand'],
            HomeHandler::class => [AppServiceFactory::class, 'makeHomeHandler'],
        ],
        'aliases' => [],
    ],
];
