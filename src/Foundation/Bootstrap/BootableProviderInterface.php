<?php

declare(strict_types=1);

namespace Framework\Foundation\Bootstrap;

/**
 * Дополнительный lifecycle-контракт для providers, которым нужен post-build boot.
 *
 * Не каждый bootstrap provider обязан участвовать в фазе boot. Этот контракт делает
 * намерение явным и убирает пустые lifecycle-методы из providers, которым boot не нужен.
 */
interface BootableProviderInterface
{
    public function boot(BootstrapContext $context): void;
}
