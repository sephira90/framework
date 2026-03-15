<?php

declare(strict_types=1);

namespace Framework\Container;

use Closure;

/**
 * Runtime representation зарегистрированного сервиса.
 *
 * Хранит сам factory, семантику shared/transient и precomputed invocation mode.
 * Это позволяет проверить contract формы factory один раз на этапе registration,
 * а в runtime не тратить reflection на каждый resolve.
 */
final readonly class ServiceDefinition
{
    public function __construct(
        public Closure $factory,
        public bool $shared,
        public bool $requiresContainer,
    ) {
    }
}
