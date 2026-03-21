<?php

declare(strict_types=1);

use Framework\Config\Env;

// Корневой application config slice. Остальные части runtime graph теперь могут
// жить в соседних config files и мержатся ConfigLoader'ом детерминированно.
return [
    'app' => [
        'name' => Env::get('APP_NAME', 'Framework'),
        'env' => Env::get('APP_ENV', 'production'),
        'debug' => Env::bool('APP_DEBUG', false),
    ],
];
