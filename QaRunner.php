<?php

declare(strict_types=1);

namespace Framework\Tooling;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Репродуцируемая оболочка вокруг обязательного QA-контура проекта.
 *
 * Runner решает две задачи:
 *
 * - нормализует запуск lint/phpcs/phpstan/psalm в текущей Windows/OSPanel
 *   среде;
 * - делает QA-процесс частью самого репозитория, а не внешним знанием.
 */
final class QaRunner
{
    /** @var list<string> */
    private const TARGET_PATHS = [
        'app',
        'bootstrap',
        'config',
        'public',
        'routes',
        'src',
        'tests',
        'tools',
        'qa.php',
        'QaRunner.php',
    ];

    /** @var list<string> */
    private const EXCLUDED_SEGMENTS = ['/vendor/', '/artifacts/', '/var/', '/.git/', '/.osp/'];

    private const TEMP_DIRECTORY = 'var/tmp';

    /**
     * @param list<string> $argv
     */
    public static function main(array $argv): int
    {
        $command = $argv[1] ?? 'qa';
        $supportedCommands = ['lint', 'cs', 'phpstan', 'psalm', 'qa'];

        if (!in_array($command, $supportedCommands, true)) {
            fwrite(STDERR, "Unsupported QA command: {$command}\n");
            return 1;
        }

        self::prepareDirectories();
        $phpFiles = self::findPhpFiles(self::TARGET_PATHS);

        if ($command !== 'qa') {
            return self::runQaStep($command, $phpFiles);
        }

        $exitCode = 0;

        foreach (['lint', 'cs', 'phpstan', 'psalm'] as $step) {
            $stepExitCode = self::runQaStep($step, $phpFiles);

            if ($stepExitCode !== 0) {
                $exitCode = $stepExitCode;
            }
        }

        return $exitCode;
    }

    /**
     * Создаёт каталоги, которые нужны QA-инструментам в этой среде.
     */
    private static function prepareDirectories(): void
    {
        foreach (
            [
                'app',
                'bootstrap',
                'config',
                'public',
                'routes',
                'src',
                'tests',
                'tools',
                'var/phpstan',
                'var/tmp',
                'var/composer-cache',
            ] as $directory
        ) {
            if (is_dir($directory)) {
                continue;
            }

            mkdir($directory, 0777, true);
        }
    }

    /**
     * @param list<string> $paths
     * @return list<string>
     */
    private static function findPhpFiles(array $paths): array
    {
        $files = [];

        foreach ($paths as $path) {
            if (is_file($path)) {
                $normalizedPath = str_replace('\\', '/', $path);

                if (str_ends_with($path, '.php') && !self::isExcludedPath($normalizedPath)) {
                    $files[] = $path;
                }

                continue;
            }

            if (!is_dir($path)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
            );

            /** @var list<SplFileInfo> $iteratorFiles */
            $iteratorFiles = iterator_to_array($iterator, false);

            foreach ($iteratorFiles as $file) {
                if (!$file->isFile()) {
                    continue;
                }

                $pathname = $file->getPathname();

                if (!str_ends_with($pathname, '.php')) {
                    continue;
                }

                $normalizedPath = str_replace('\\', '/', $pathname);

                if (self::isExcludedPath($normalizedPath)) {
                    continue;
                }

                $files[] = $pathname;
            }
        }

        $files = array_values(array_unique($files));
        sort($files);

        return $files;
    }

    /**
     * Исключает vendor, артефакты и служебные каталоги из QA-сканирования.
     */
    private static function isExcludedPath(string $path): bool
    {
        foreach (self::EXCLUDED_SEGMENTS as $segment) {
            if (str_contains($path, $segment)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $phpFiles
     */
    private static function runQaStep(string $step, array $phpFiles): int
    {
        if ($phpFiles === []) {
            $message = sprintf(
                'No PHP files found in app, bootstrap, config, public, routes, src, tests, tools, qa.php, '
                . 'QaRunner.php. Skipping %s.' . PHP_EOL,
                $step
            );

            fwrite(
                STDOUT,
                $message
            );
            return 0;
        }

        $command = match ($step) {
            'lint' => self::buildCommand([
                PHP_BINARY,
                '-d',
                'sys_temp_dir=' . self::TEMP_DIRECTORY,
                'vendor/bin/parallel-lint',
                'app',
                'bootstrap',
                'config',
                'public',
                'routes',
                'src',
                'tests',
                'tools',
                'qa.php',
                'QaRunner.php',
                '--exclude',
                'vendor',
                '--exclude',
                'artifacts',
                '--exclude',
                '.git',
                '--exclude',
                '.osp',
                '--exclude',
                'var',
            ]),
            'cs' => self::buildCommand([
                PHP_BINARY,
                '-d',
                'sys_temp_dir=' . self::TEMP_DIRECTORY,
                'vendor/bin/phpcs',
                '--standard=phpcs.xml.dist',
            ]),
            'phpstan' => self::buildCommand([
                PHP_BINARY,
                '-d',
                'sys_temp_dir=' . self::TEMP_DIRECTORY,
                'vendor/bin/phpstan',
                'analyse',
                '--configuration=phpstan.neon.dist',
                '--memory-limit=1G',
            ]),
            'psalm' => self::buildCommand([
                PHP_BINARY,
                '-d',
                'sys_temp_dir=' . self::TEMP_DIRECTORY,
                'vendor/bin/psalm',
                '--config=psalm.xml',
                '--no-cache',
                '--show-info=false',
            ]),
            default => null,
        };

        if ($command === null) {
            fwrite(STDERR, "Unsupported QA step: {$step}\n");
            return 1;
        }

        passthru($command, $exitCode);

        return $exitCode;
    }

    /**
     * Экранирует аргументы так, чтобы команды одинаково воспроизводились из
     * PHP без зависимости от пользовательского shell state.
     *
     * @param list<string> $parts
     */
    private static function buildCommand(array $parts): string
    {
        return implode(
            ' ',
            array_map(static fn (string $part): string => escapeshellarg($part), $parts)
        );
    }
}
