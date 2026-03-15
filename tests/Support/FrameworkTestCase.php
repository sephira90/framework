<?php

declare(strict_types=1);

namespace Framework\Tests\Support;

use PHPUnit\Framework\TestCase;

/**
 * Базовый test case для framework tests.
 *
 * Даёт утилиты для временных project trees, чтобы тесты могли собирать runtime
 * в изоляции и не полагаться на состояние рабочего каталога.
 */
abstract class FrameworkTestCase extends TestCase
{
    /** @var list<string> */
    private array $temporaryDirectories = [];

    /**
     * Создаёт изолированную временную директорию для тестового проекта.
     */
    protected function createTempDirectory(): string
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'framework-tests-' . bin2hex(random_bytes(8));

        mkdir($directory, 0777, true);
        $this->temporaryDirectories[] = $directory;

        return $directory;
    }

    /**
     * Записывает файл внутрь тестового project tree, создавая каталоги по пути.
     */
    protected function writeFile(string $basePath, string $relativePath, string $contents): void
    {
        $path = $basePath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($path, $contents);
    }

    /**
     * После каждого теста очищает все созданные временные project trees.
     */
    #[\Override]
    protected function tearDown(): void
    {
        foreach ($this->temporaryDirectories as $directory) {
            $this->removeDirectory($directory);
        }

        $this->temporaryDirectories = [];

        parent::tearDown();
    }

    /**
     * Рекурсивно удаляет временную директорию теста.
     */
    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path . DIRECTORY_SEPARATOR . $item;

            if (is_dir($itemPath)) {
                $this->removeDirectory($itemPath);
                continue;
            }

            unlink($itemPath);
        }

        rmdir($path);
    }
}
