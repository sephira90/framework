<?php

declare(strict_types=1);

namespace Framework\Config;

use InvalidArgumentException;

/**
 * Сигнализирует о структурно некорректной конфигурации framework/application.
 */
final class InvalidConfigurationException extends InvalidArgumentException
{
}
