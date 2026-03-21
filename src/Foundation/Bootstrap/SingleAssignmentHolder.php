<?php

declare(strict_types=1);

namespace Framework\Foundation\Bootstrap;

/**
 * @template TValue
 *
 * Минимизирует дублирование single-assignment lifecycle contract в bootstrap
 * registries, но не заменяет сами именованные registry boundary.
 */
final class SingleAssignmentHolder
{
    private bool $initialized = false;

    /** @var TValue|null */
    private mixed $value = null;

    public function __construct(
        private string $subject,
    ) {
    }

    /**
     * @param TValue $value
     */
    public function initialize(mixed $value): void
    {
        if ($this->initialized) {
            throw new BootstrapStateException(sprintf('%s has already been initialized.', $this->subject));
        }

        $this->value = $value;
        $this->initialized = true;
    }

    /**
     * @return TValue
     */
    public function get(): mixed
    {
        if (!$this->initialized) {
            throw new BootstrapStateException(sprintf('%s has not been initialized yet.', $this->subject));
        }

        /** @var TValue $value */
        $value = $this->value;

        return $value;
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }
}
