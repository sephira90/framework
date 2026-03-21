<?php

declare(strict_types=1);

namespace Framework\Console;

use InvalidArgumentException;

/**
 * Тонкий output adapter над stdout/stderr, пригодный для тестов.
 */
final class ConsoleOutput
{
    /**
     * @var resource
     */
    private mixed $stdout;

    /**
     * @var resource
     */
    private mixed $stderr;

    /**
     * @param resource|null $stdout
     * @param resource|null $stderr
     */
    public function __construct(mixed $stdout = null, mixed $stderr = null)
    {
        $this->stdout = $this->normalizeStream($stdout, 'php://stdout', 'stdout');
        $this->stderr = $this->normalizeStream($stderr, 'php://stderr', 'stderr');
    }

    public function write(string $text): void
    {
        fwrite($this->stdout, $text);
    }

    public function writeln(string $text = ''): void
    {
        $this->write($text . PHP_EOL);
    }

    public function writeError(string $text): void
    {
        fwrite($this->stderr, $text);
    }

    public function writeErrorLine(string $text = ''): void
    {
        $this->writeError($text . PHP_EOL);
    }

    /**
     * @return resource
     */
    private function normalizeStream(mixed $stream, string $defaultTarget, string $name): mixed
    {
        if ($stream === null) {
            $opened = fopen($defaultTarget, 'wb');

            if ($opened === false) {
                throw new InvalidArgumentException(sprintf('Unable to open default %s stream.', $name));
            }

            return $opened;
        }

        if (!is_resource($stream)) {
            throw new InvalidArgumentException(sprintf('Console %s stream must be a resource.', $name));
        }

        return $stream;
    }
}
