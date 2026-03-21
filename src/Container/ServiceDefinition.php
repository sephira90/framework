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
        private Closure $factory,
        private bool $shared,
        private bool $requiresContainer,
    ) {
    }

    /**
     * Возвращает factory, уже провалидированный builder'ом.
     */
    public function factory(): Closure
    {
        return $this->factory;
    }

    /**
     * Сообщает, должен ли resolved instance кэшироваться контейнером.
     */
    public function isShared(): bool
    {
        return $this->shared;
    }

    /**
     * Возвращает предвычисленный invocation mode factory.
     */
    public function requiresContainer(): bool
    {
        return $this->requiresContainer;
    }
}
