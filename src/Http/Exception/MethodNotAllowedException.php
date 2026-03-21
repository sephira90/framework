<?php

declare(strict_types=1);

namespace Framework\Http\Exception;

use Framework\Http\AllowedMethods;

/**
 * Контролируемая `405 Method Not Allowed`.
 */
final class MethodNotAllowedException extends HttpException
{
    /** @var list<string> */
    private array $allowedMethods;

    /**
     * @param list<string> $allowedMethods
     */
    public function __construct(array $allowedMethods, string $message = '')
    {
        $this->allowedMethods = AllowedMethods::normalize($allowedMethods);

        parent::__construct($message, [
            'Allow' => implode(', ', $this->allowedMethods),
        ]);
    }

    #[\Override]
    public function statusCode(): int
    {
        return 405;
    }

    /**
     * @return list<string>
     */
    public function allowedMethods(): array
    {
        return $this->allowedMethods;
    }

    #[\Override]
    protected function defaultMessage(): string
    {
        return 'Method Not Allowed';
    }
}
