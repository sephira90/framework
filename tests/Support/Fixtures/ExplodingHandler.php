<?php

declare(strict_types=1);

namespace Framework\Tests\Support\Fixtures;

use RuntimeException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ExplodingHandler implements RequestHandlerInterface
{
    #[\Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw new RuntimeException('boom');
    }
}
