<?php

declare(strict_types=1);

namespace Framework\Http\Exception;

/**
 * Контролируемая `404 Not Found`.
 */
final class NotFoundException extends HttpException
{
    #[\Override]
    public function statusCode(): int
    {
        return 404;
    }

    #[\Override]
    protected function defaultMessage(): string
    {
        return 'Not Found';
    }
}
