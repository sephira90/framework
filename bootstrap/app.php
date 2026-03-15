<?php

declare(strict_types=1);

use Framework\Foundation\ApplicationFactory;

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Bootstrap runtime один раз из корня проекта и возвращает уже собранный
// HttpRuntime для front controller'а.
return ApplicationFactory::createRuntime(dirname(__DIR__));
