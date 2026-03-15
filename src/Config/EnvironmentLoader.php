<?php

declare(strict_types=1);

namespace Framework\Config;

use Dotenv\Dotenv;

/**
 * Поднимает `.env` до чтения конфигурации.
 *
 * Загрузчик намеренно не хранит process-level state: если вызывающий код
 * очищает окружение между bootstrap-циклами, тот же base path может быть
 * безопасно загружен повторно в том же процессе.
 */
final class EnvironmentLoader
{
    /**
     * Безопасно загружает `.env`, если он существует.
     */
    public function load(string $basePath): void
    {
        $envPath = $basePath . DIRECTORY_SEPARATOR . '.env';

        if (is_file($envPath)) {
            Dotenv::createImmutable($basePath)->safeLoad();
        }
    }
}
