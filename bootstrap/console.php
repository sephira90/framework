<?php

declare(strict_types=1);

use Framework\Foundation\ConsoleApplicationFactory;
use Framework\Foundation\ConsoleRuntime;

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Bootstrap console runtime один раз из корня проекта и возвращает уже
// собранный ConsoleRuntime для CLI entrypoint'а.
return (static function (): ConsoleRuntime {
    return ConsoleApplicationFactory::createRuntime(dirname(__DIR__));
})();
