<?php

declare(strict_types=1);

namespace Framework\Tests\Support\Fixtures;

use Framework\Routing\RouteAttributes;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class StackHandler implements RequestHandlerInterface
{
    #[\Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var list<string> $stack */
        $stack = $request->getAttribute(RouteAttributes::PARAMS . '.stack', []);

        if ($stack === []) {
            /** @var list<string> $stack */
            $stack = $request->getAttribute('stack', []);
        }

        $stack[] = 'handler';

        return new Response(200, ['Content-Type' => 'text/plain; charset=utf-8'], implode('>', $stack));
    }
}
