<?php

declare(strict_types=1);

require_once __DIR__ . '/QaRunner.php';

// Тонкий CLI entry point для единообразного запуска QA-контура проекта.
exit(Framework\Tooling\QaRunner::main($argv));
