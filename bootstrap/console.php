<?php

declare(strict_types=1);

use Framework\Foundation\ConsoleApplicationFactory;
use Framework\Foundation\ConsoleRuntime;
use Framework\Console\ArgvInputFactory;

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Bootstrap console runtime один раз из корня проекта и возвращает уже
// собранный ConsoleRuntime для CLI entrypoint'а.
return (static function (): ConsoleRuntime {
    $basePath = dirname(__DIR__);
    /**
     * @param mixed $rawArgv
     * @return list<string>
     */
    $normalizeArgv = static function (mixed $rawArgv): array {
        if (!is_array($rawArgv)) {
            return [];
        }

        $argv = [];

        foreach ($rawArgv as $token) {
            if (!is_string($token)) {
                continue;
            }

            $argv[] = $token;
        }

        return $argv;
    };
    $input = (new ArgvInputFactory())->fromArgv($normalizeArgv($_SERVER['argv'] ?? []));

    if ($input->commandName() === 'cache:clear') {
        return ConsoleApplicationFactory::createRecoveryRuntime($basePath);
    }

    return ConsoleApplicationFactory::createRuntime($basePath);
})();
