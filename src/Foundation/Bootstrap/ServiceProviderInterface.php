<?php

declare(strict_types=1);

namespace Framework\Foundation\Bootstrap;

/**
 * Внутренний register-контракт bootstrap providers framework.
 *
 * Этот контракт не является public extension API приложения.
 */
interface ServiceProviderInterface
{
    public function register(BootstrapBuilder $builder): void;
}
