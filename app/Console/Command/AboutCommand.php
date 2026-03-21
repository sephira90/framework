<?php

declare(strict_types=1);

namespace App\Console\Command;

use Framework\Config\Config;
use Framework\Console\CommandInput;
use Framework\Console\CommandInterface;
use Framework\Console\ConsoleOutput;
use Override;

/**
 * Минимальная прикладная команда, подтверждающая, что console runtime собран.
 */
final readonly class AboutCommand implements CommandInterface
{
    public function __construct(
        private Config $config,
    ) {
    }

    #[Override]
    public function execute(CommandInput $input, ConsoleOutput $output): int
    {
        unset($input);

        $output->writeln(sprintf(
            '%s console kernel is running in [%s].',
            self::stringConfigValue($this->config->get('app.name', 'Framework'), 'Framework'),
            self::stringConfigValue($this->config->get('app.env', 'production'), 'production')
        ));

        return 0;
    }

    private static function stringConfigValue(mixed $value, string $default): string
    {
        return is_string($value) ? $value : $default;
    }
}
