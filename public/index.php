<?php

declare(strict_types=1);

use Framework\Foundation\HttpRuntime;

$runtime = (static function (): HttpRuntime {
    $runtime = require dirname(__DIR__) . '/bootstrap/app.php';

    /** @psalm-suppress RedundantCondition */
    if (!$runtime instanceof HttpRuntime) {
        throw new RuntimeException('Bootstrap must return an HttpRuntime instance.');
    }

    return $runtime;
})();

// Front controller выполняет только три вещи: получает runtime, создаёт
// request из globals и эмитит response.
$response = $runtime
    ->application()
    ->handle($runtime->requestFactory()->fromGlobals());

$runtime->responseEmitter()->emit($response);
