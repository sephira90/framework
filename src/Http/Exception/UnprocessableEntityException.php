<?php

declare(strict_types=1);

namespace Framework\Http\Exception;

/**
 * Контролируемая `422 Unprocessable Entity`.
 */
final class UnprocessableEntityException extends HttpException
{
    #[\Override]
    public function statusCode(): int
    {
        return 422;
    }

    #[\Override]
    protected function defaultMessage(): string
    {
        return 'Unprocessable Entity';
    }
}
