<?php

declare(strict_types=1);

namespace Framework\Foundation\Bootstrap;

use Framework\Config\Config;
use Framework\Config\InvalidConfigurationException;
use Framework\Http\Middleware\ErrorHandlingMiddleware;
use Psr\Http\Server\MiddlewareInterface;

/**
 * Нормализует configured global middleware stack для bootstrap state.
 */
final class GlobalMiddlewareFactory
{
    /**
     * @return list<class-string<MiddlewareInterface>|MiddlewareInterface>
     */
    public function create(Config $config): array
    {
        $configuredMiddleware = $config->get('middleware', []);

        if (!is_array($configuredMiddleware)) {
            throw new InvalidConfigurationException('Configuration key [middleware] must be an array.');
        }

        /** @var list<class-string<MiddlewareInterface>|MiddlewareInterface> $middleware */
        $middleware = [ErrorHandlingMiddleware::class];

        foreach ($configuredMiddleware as $definition) {
            if ($definition instanceof MiddlewareInterface) {
                $middleware[] = $definition;

                continue;
            }

            if (!is_string($definition)) {
                throw new InvalidConfigurationException(
                    'Global middleware definitions must be class strings or MiddlewareInterface instances.'
                );
            }

            if (!class_exists($definition)) {
                throw new InvalidConfigurationException(sprintf(
                    'Global middleware class [%s] does not exist.',
                    $definition
                ));
            }

            if (!is_a($definition, MiddlewareInterface::class, true)) {
                throw new InvalidConfigurationException(sprintf(
                    'Global middleware class [%s] must implement MiddlewareInterface.',
                    $definition
                ));
            }

            $middleware[] = $definition;
        }

        return $middleware;
    }
}
