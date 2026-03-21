<?php

declare(strict_types=1);

namespace Framework\Http\Exception;

/**
 * Контролируемая `403 Forbidden`.
 */
final class ForbiddenException extends HttpException
{
    #[\Override]
    public function statusCode(): int
    {
        return 403;
    }

    #[\Override]
    protected function defaultMessage(): string
    {
        return 'Forbidden';
    }
}
