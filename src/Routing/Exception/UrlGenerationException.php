<?php

declare(strict_types=1);

namespace Framework\Routing\Exception;

use InvalidArgumentException;

/**
 * Signals a reverse-routing failure.
 *
 * This exception is intentionally narrower than InvalidArgumentException:
 * route registration/definition errors stay on the configuration side, while
 * URL generation failures get one dedicated catch surface.
 */
final class UrlGenerationException extends InvalidArgumentException
{
    public static function unknownRouteName(string $name): self
    {
        return new self(sprintf('Route name [%s] is not registered.', $name));
    }

    public static function missingParameter(string $path, string $parameterName): self
    {
        return new self(sprintf(
            'Route [%s] requires parameter [%s] for URL generation.',
            $path,
            $parameterName
        ));
    }

    public static function constraintViolation(string $path, string $parameterName, string $constraint): self
    {
        return new self(sprintf(
            'Route [%s] requires parameter [%s] to match constraint [%s] for URL generation.',
            $path,
            $parameterName,
            $constraint
        ));
    }

    /**
     * @param list<string> $parameterNames
     */
    public static function unexpectedParameters(string $path, array $parameterNames): self
    {
        return new self(sprintf(
            'Route [%s] does not define parameter(s) [%s] for URL generation.',
            $path,
            implode(', ', $parameterNames)
        ));
    }
}
