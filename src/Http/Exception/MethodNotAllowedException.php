<?php

declare(strict_types=1);

namespace Framework\Http\Exception;

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
        $this->allowedMethods = self::normalizeAllowedMethods($allowedMethods);

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

    /**
     * @param list<string> $allowedMethods
     * @return list<string>
     */
    private static function normalizeAllowedMethods(array $allowedMethods): array
    {
        $normalizedMethods = array_values(array_unique(array_map(
            static fn (string $method): string => strtoupper($method),
            $allowedMethods
        )));

        if (in_array('GET', $normalizedMethods, true) && !in_array('HEAD', $normalizedMethods, true)) {
            $normalizedMethods[] = 'HEAD';
        }

        sort($normalizedMethods);

        return $normalizedMethods;
    }
}
