<?php

declare(strict_types=1);

namespace Framework\Routing;

use Framework\Routing\Exception\UrlGenerationException;
use InvalidArgumentException;

/**
 * Precompiles a normalized route path into one reusable contract for matching,
 * parameter extraction, and URL generation.
 *
 * This keeps route-template causality in one place: the same parameter model
 * that accepts an incoming path also validates values used during URL
 * generation.
 */
final readonly class CompiledRoutePath
{
    /**
     * @var list<array{
     *     name: string,
     *     placeholder: string,
     *     constraint: string|null,
     *     validationRegex: non-empty-string|null
     * }>
     */
    private array $parameters;

    /**
     * @param non-empty-string $matchingRegex
     * @param list<array{
     *     name: string,
     *     placeholder: string,
     *     constraint: string|null,
     *     validationRegex: non-empty-string|null
     * }> $parameters
     */
    private function __construct(
        private string $path,
        private string $matchingRegex,
        array $parameters,
        private bool $static,
        private int $segmentCount,
        private ?string $firstLiteralSegment,
    ) {
        $this->parameters = $parameters;
    }

    public static function fromNormalizedPath(string $path): self
    {
        if ($path === '/') {
            return new self('/', '#^/$#', [], true, 0, null);
        }

        $segments = explode('/', trim($path, '/'));
        $parameters = [];
        $patternSegments = [];
        $firstLiteralSegment = null;
        /** @var array<string, true> $seenParameters */
        $seenParameters = [];

        foreach ($segments as $segment) {
            $parameter = self::compileParameter($segment, $path);

            if ($parameter === null) {
                $patternSegments[] = preg_quote($segment, '#');

                if ($firstLiteralSegment === null) {
                    $firstLiteralSegment = $segment;
                }

                continue;
            }

            if (isset($seenParameters[$parameter['name']])) {
                throw new InvalidArgumentException(sprintf(
                    'Route path [%s] contains duplicate parameter names.',
                    $path
                ));
            }

            $seenParameters[$parameter['name']] = true;
            $parameters[] = $parameter;
            $patternSegments[] = sprintf('(?P<%s>[^/]+)', $parameter['name']);
        }

        if ($parameters === []) {
            return new self(
                $path,
                '#^' . preg_quote($path, '#') . '$#',
                [],
                true,
                count($segments),
                $firstLiteralSegment
            );
        }

        return new self(
            $path,
            '#^/' . implode('/', $patternSegments) . '$#',
            $parameters,
            false,
            count($segments),
            $firstLiteralSegment
        );
    }

    /**
     * @param array{
     *     path: string,
     *     matching_regex: non-empty-string,
     *     parameters: list<array{
     *         name: string,
     *         placeholder: string,
     *         constraint: string|null,
     *         validationRegex: non-empty-string|null
     *     }>,
     *     static: bool,
     *     segment_count: int,
     *     first_literal_segment: string|null
     * } $data
     */
    public static function fromExport(array $data): self
    {
        return new self(
            $data['path'],
            $data['matching_regex'],
            $data['parameters'],
            $data['static'],
            $data['segment_count'],
            $data['first_literal_segment']
        );
    }

    public function isStatic(): bool
    {
        return $this->static;
    }

    public function segmentCount(): int
    {
        return $this->segmentCount;
    }

    public function firstLiteralSegment(): ?string
    {
        return $this->firstLiteralSegment;
    }

    /**
     * @return array<string, string>|null
     */
    public function matchNormalizedPath(string $path): ?array
    {
        if ($this->static) {
            return $this->path === $path ? [] : null;
        }

        return $this->extractDynamicParameters($path);
    }

    public function matchesNormalizedPath(string $path): bool
    {
        return $this->matchNormalizedPath($path) !== null;
    }

    /**
     * @return array<string, string>
     */
    public function extractParametersFromNormalizedPath(string $path): array
    {
        return $this->matchNormalizedPath($path) ?? [];
    }

    /**
     * @param array<string, string|int|float> $parameters
     */
    public function generatePath(array $parameters): string
    {
        $path = $this->path;

        foreach ($this->parameters as $parameter) {
            $name = $parameter['name'];

            if (!array_key_exists($name, $parameters)) {
                throw UrlGenerationException::missingParameter($this->path, $name);
            }

            $value = (string) $parameters[$name];

            if (!$this->matchesConstraint($parameter, $value)) {
                $constraint = $parameter['constraint'] ?? 'unknown';

                throw UrlGenerationException::constraintViolation($this->path, $name, $constraint);
            }

            $path = str_replace($parameter['placeholder'], rawurlencode($value), $path);
        }

        $this->assertNoUnexpectedParameters($parameters);

        return $path;
    }

    /**
     * @return array<string, string>|null
     */
    private function extractDynamicParameters(string $path): ?array
    {
        $matches = [];

        if (preg_match($this->matchingRegex, $path, $matches) !== 1) {
            return null;
        }

        $parameters = [];

        foreach ($this->parameters as $parameter) {
            $rawValue = $matches[$parameter['name']] ?? null;

            if (!is_string($rawValue)) {
                return null;
            }

            $value = rawurldecode($rawValue);

            if (!$this->matchesConstraint($parameter, $value)) {
                return null;
            }

            $parameters[$parameter['name']] = $value;
        }

        return $parameters;
    }

    /**
     * @param array{
     *     name: string,
     *     placeholder: string,
     *     constraint: string|null,
     *     validationRegex: non-empty-string|null
     * } $parameter
     */
    private function matchesConstraint(array $parameter, string $value): bool
    {
        $validationRegex = $parameter['validationRegex'];

        if ($validationRegex === null) {
            return true;
        }

        return preg_match($validationRegex, $value) === 1;
    }

    /**
     * @param array<string, string|int|float> $parameters
     */
    private function assertNoUnexpectedParameters(array $parameters): void
    {
        $expectedParameters = [];

        foreach ($this->parameters as $parameter) {
            $expectedParameters[] = $parameter['name'];
        }

        $unexpectedParameters = array_values(array_diff(array_keys($parameters), $expectedParameters));

        if ($unexpectedParameters === []) {
            return;
        }

        throw UrlGenerationException::unexpectedParameters($this->path, $unexpectedParameters);
    }

    /**
     * @return array{
     *     path: string,
     *     matching_regex: non-empty-string,
     *     parameters: list<array{
     *         name: string,
     *         placeholder: string,
     *         constraint: string|null,
     *         validationRegex: non-empty-string|null
     *     }>,
     *     static: bool,
     *     segment_count: int,
     *     first_literal_segment: string|null
     * }
     */
    public function export(): array
    {
        return [
            'path' => $this->path,
            'matching_regex' => $this->matchingRegex,
            'parameters' => $this->parameters,
            'static' => $this->static,
            'segment_count' => $this->segmentCount,
            'first_literal_segment' => $this->firstLiteralSegment,
        ];
    }

    /**
     * @return array{
     *     name: string,
     *     placeholder: string,
     *     constraint: string|null,
     *     validationRegex: non-empty-string|null
     * }|null
     */
    private static function compileParameter(string $segment, string $path): ?array
    {
        if (!str_starts_with($segment, '{') && !str_ends_with($segment, '}')) {
            return null;
        }

        $matches = [];

        if (preg_match('/^\{([A-Za-z_][A-Za-z0-9_]*)(?::(.+))?\}$/', $segment, $matches) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'Route path [%s] contains invalid parameter segment [%s].',
                $path,
                $segment
            ));
        }

        /** @var array{0: non-falsy-string, 1: non-falsy-string, 2?: non-empty-string} $matches */
        $constraint = $matches[2] ?? null;

        return [
            'name' => $matches[1],
            'placeholder' => $matches[0],
            'constraint' => $constraint,
            'validationRegex' => $constraint !== null
                ? self::compileConstraintRegex($path, $matches[1], $constraint)
                : null,
        ];
    }

    /**
     * @return non-empty-string
     */
    private static function compileConstraintRegex(string $path, string $parameterName, string $constraint): string
    {
        $delimiter = '#';
        $escapedConstraint = str_replace($delimiter, '\\' . $delimiter, $constraint);
        $validationRegex = $delimiter . '^(?:' . $escapedConstraint . ')$' . $delimiter . 'u';

        if (@preg_match($validationRegex, '') === false) {
            throw new InvalidArgumentException(sprintf(
                'Route path [%s] contains invalid constraint [%s] for parameter [%s].',
                $path,
                $constraint,
                $parameterName
            ));
        }

        return $validationRegex;
    }
}
