<?php

declare(strict_types=1);

namespace Framework\Foundation;

use Framework\Console\ArgvInputFactory;
use Framework\Console\ConsoleApplication;

/**
 * Снимок уже собранного CLI runtime.
 */
final readonly class ConsoleRuntime
{
    public function __construct(
        private ConsoleApplication $application,
        private ArgvInputFactory $inputFactory,
    ) {
    }

    public function application(): ConsoleApplication
    {
        return $this->application;
    }

    public function inputFactory(): ArgvInputFactory
    {
        return $this->inputFactory;
    }
}
