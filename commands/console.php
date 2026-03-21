<?php

declare(strict_types=1);

use App\Console\Command\AboutCommand;
use Framework\Console\CommandCollector;

// Commands file не строит runtime и не содержит parser/error machinery. Его
// единственная задача — явно зарегистрировать app commands через CommandCollector.
return static function (CommandCollector $commands): void {
    $commands->command('app:about', AboutCommand::class);
};
