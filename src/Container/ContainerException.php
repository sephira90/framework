<?php

declare(strict_types=1);

namespace Framework\Container;

use Psr\Container\ContainerExceptionInterface;
use RuntimeException;

/**
 * Базовая ошибка контейнера для resolution и alias graph problems.
 */
class ContainerException extends RuntimeException implements ContainerExceptionInterface
{
}
