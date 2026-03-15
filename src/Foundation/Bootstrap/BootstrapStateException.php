<?php

declare(strict_types=1);

namespace Framework\Foundation\Bootstrap;

use LogicException;

/**
 * Сигнализирует о нарушении внутреннего bootstrap lifecycle.
 */
final class BootstrapStateException extends LogicException
{
}
