<?php

declare(strict_types=1);

// Console slice хранит только registration seam для CLI commands.
return [
    'console' => [
        'commands' => 'commands/console.php',
    ],
];
