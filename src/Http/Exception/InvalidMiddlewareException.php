<?php

declare(strict_types=1);

namespace Framework\Http\Exception;

use RuntimeException;

/**
 * Ошибка нарушения middleware contract.
 */
final class InvalidMiddlewareException extends RuntimeException
{
}
