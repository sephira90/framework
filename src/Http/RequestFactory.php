<?php

declare(strict_types=1);

namespace Framework\Http;

use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Обёртка над PSR-7 server request creation из PHP globals.
 */
final readonly class RequestFactory
{
    public function __construct(
        private ServerRequestCreator $creator,
    ) {
    }

    /**
     * Создаёт PSR-7 request из текущего состояния SAPI.
     */
    public function fromGlobals(): ServerRequestInterface
    {
        return $this->creator->fromGlobals();
    }
}
