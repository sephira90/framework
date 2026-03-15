<?php

declare(strict_types=1);

namespace Framework\Foundation;

use Framework\Http\RequestFactory;
use Framework\Http\ResponseEmitter;

/**
 * Снимок уже собранного runtime.
 *
 * Объект объединяет три внешних компонента, нужных front controller'у:
 * application, request factory и response emitter. Он не строит ничего сам,
 * а только хранит готовую связку.
 */
final readonly class HttpRuntime
{
    public function __construct(
        private Application $application,
        private RequestFactory $requestFactory,
        private ResponseEmitter $responseEmitter,
    ) {
    }

    /**
     * Возвращает top-level HTTP kernel.
     */
    public function application(): Application
    {
        return $this->application;
    }

    /**
     * Возвращает фабрику создания request из SAPI/globals.
     */
    public function requestFactory(): RequestFactory
    {
        return $this->requestFactory;
    }

    /**
     * Возвращает компонент отправки PSR-7 response в PHP runtime.
     */
    public function responseEmitter(): ResponseEmitter
    {
        return $this->responseEmitter;
    }
}
