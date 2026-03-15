<?php

declare(strict_types=1);

namespace Framework\Tests\Support\Fixtures;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RouteMiddleware implements MiddlewareInterface
{
    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $stack = self::extractStack($request->getAttribute('stack', []));
        $stack[] = 'route';

        return $handler->handle($request->withAttribute('stack', $stack));
    }

    /**
     * @return list<string>
     */
    private static function extractStack(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $stack = array_values(array_filter($value, static fn (mixed $entry): bool => is_string($entry)));

        return $stack;
    }
}
