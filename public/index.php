<?php

declare(strict_types=1);

use Framework\Foundation\HttpRuntime;

$runtime = (static function (): HttpRuntime {
    $runtime = require dirname(__DIR__) . '/bootstrap/app.php';

    if (!$runtime instanceof HttpRuntime) {
        throw new RuntimeException('Bootstrap must return an HttpRuntime instance.');
    }

    return $runtime;
})();

// Front controller выполняет только три вещи: получает runtime, создаёт
// request из globals и эмитит response.
$request = $runtime->requestFactory()->fromGlobals();
$response = $runtime->application()->handle($request);

// `HEAD` делит route semantics с `GET`, но transport boundary не должен
// отправлять body клиенту даже если response object его содержит.
$runtime->responseEmitter()->emit(
    $response,
    emitBody: strcasecmp($request->getMethod(), 'HEAD') !== 0
);
