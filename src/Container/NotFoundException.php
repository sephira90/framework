<?php

declare(strict_types=1);

namespace Framework\Container;

use Psr\Container\NotFoundExceptionInterface;

/**
 * Ошибка запроса неизвестного сервиса.
 */
final class NotFoundException extends ContainerException implements NotFoundExceptionInterface
{
}
