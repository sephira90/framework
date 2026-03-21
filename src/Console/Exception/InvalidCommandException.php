<?php

declare(strict_types=1);

namespace Framework\Console\Exception;

use RuntimeException;

/**
 * Сигнализирует о нарушении command contract в runtime.
 */
final class InvalidCommandException extends RuntimeException
{
}
