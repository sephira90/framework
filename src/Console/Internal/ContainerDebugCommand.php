<?php

declare(strict_types=1);

namespace Framework\Console\Internal;

use Framework\Console\CommandInput;
use Framework\Console\CommandInterface;
use Framework\Console\ConsoleOutput;
use Framework\Foundation\ConsoleApplicationFactory;
use JsonException;
use Override;

/**
 * Prints the observable console container graph without resolving services.
 */
final readonly class ContainerDebugCommand implements CommandInterface
{
    public function __construct(
        private string $basePath,
    ) {
    }

    #[Override]
    public function execute(CommandInput $input, ConsoleOutput $output): int
    {
        $snapshot = $input->option('source', false) === true
            ? ConsoleApplicationFactory::inspectSource($this->basePath)
            : ConsoleApplicationFactory::inspectRuntime($this->basePath);
        $id = $input->argument(0);

        if ($id !== null) {
            $entry = $snapshot->exportEntry($id);

            if ($entry === null) {
                $output->writeErrorLine(sprintf('Container entry [%s] was not found.', $id));
                return 1;
            }

            $output->writeln($this->encodeJson($entry));

            return 0;
        }

        $output->writeln($this->encodeJson($snapshot->export()));

        return 0;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function encodeJson(array $payload): string
    {
        try {
            return json_encode(
                $payload,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new \RuntimeException('Unable to encode container inspection payload as JSON.', 0, $exception);
        }
    }
}
