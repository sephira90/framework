<?php

declare(strict_types=1);

namespace Framework\Routing;

use Closure;
use Framework\Routing\Exception\UrlGenerationException;
use InvalidArgumentException;
use Psr\Http\Server\MiddlewareInterface;

/**
 * HTTP route definition.
 *
 * Route owns one compiled path contract that is reused for:
 *
 * - normalized path storage;
 * - incoming path matching;
 * - parameter extraction;
 * - URL generation validation.
 *
 * This keeps matching and generation aligned instead of letting two separate
 * code paths drift into different parameter rules over time.
 */
final readonly class Route
{
    /** @var list<string> */
    private array $methods;

    /** @var array<string, true> */
    private array $methodLookup;

    private string $path;

    private CompiledRoutePath $compiledPath;

    private Closure|string $handler;

    private ?string $name;

    private bool $static;

    private bool $allowsHeadFallback;

    /**
     * @param list<string> $methods
     * @param list<class-string<MiddlewareInterface>|MiddlewareInterface> $middleware
     */
    public function __construct(
        array $methods,
        string $path,
        callable|string $handler,
        private array $middleware = [],
        ?string $name = null,
        ?CompiledRoutePath $compiledPath = null,
    ) {
        $normalizedMethods = array_values(array_unique(array_map(
            static fn (string $method): string => strtoupper($method),
            $methods
        )));

        if ($normalizedMethods === []) {
            throw new InvalidArgumentException('Route must define at least one HTTP method.');
        }

        $this->methods = $normalizedMethods;
        $this->methodLookup = array_fill_keys($normalizedMethods, true);
        $this->path = self::normalizePath($path);
        $this->compiledPath = $compiledPath ?? CompiledRoutePath::fromNormalizedPath($this->path);
        $this->handler = is_string($handler) ? $handler : Closure::fromCallable($handler);
        $this->name = $name !== null ? self::normalizeName($name) : null;
        $this->static = $this->compiledPath->isStatic();
        $this->allowsHeadFallback = in_array('GET', $normalizedMethods, true);
    }

    /**
     * Brings the input path to one canonical representation.
     */
    public static function normalizePath(string $path): string
    {
        $trimmed = trim($path);

        if ($trimmed === '') {
            return '/';
        }

        $normalized = '/' . ltrim($trimmed, '/');
        $normalized = preg_replace('#/+#', '/', $normalized) ?? $normalized;

        if ($normalized !== '/') {
            $normalized = rtrim($normalized, '/');
        }

        return $normalized;
    }

    /**
     * @return list<string>
     */
    public function methods(): array
    {
        return $this->methods;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function handler(): Closure|string
    {
        return $this->handler;
    }

    public function name(): ?string
    {
        return $this->name;
    }

    /**
     * @return list<class-string<MiddlewareInterface>|MiddlewareInterface>
     */
    public function middleware(): array
    {
        return $this->middleware;
    }

    public function isStatic(): bool
    {
        return $this->static;
    }

    public function segmentCount(): int
    {
        return $this->compiledPath->segmentCount();
    }

    public function firstLiteralSegment(): ?string
    {
        return $this->compiledPath->firstLiteralSegment();
    }

    public function supportsMethod(string $normalizedMethod): bool
    {
        return isset($this->methodLookup[$normalizedMethod]);
    }

    public function supportsHeadFallbackFor(string $normalizedMethod): bool
    {
        return $normalizedMethod === 'HEAD' && $this->allowsHeadFallback;
    }

    /**
     * Returns a new route instance with a name used for URL generation.
     */
    public function withName(string $name): self
    {
        return new self($this->methods, $this->path, $this->handler, $this->middleware, $name);
    }

    /**
     * Checks whether an already-normalized path matches this route.
     */
    public function matchesNormalizedPath(string $path): bool
    {
        return $this->compiledPath->matchesNormalizedPath($path);
    }

    /**
     * @return array<string, string>|null
     */
    public function matchNormalizedPath(string $path): ?array
    {
        return $this->compiledPath->matchNormalizedPath($path);
    }

    /**
     * Extracts route parameters from an already-normalized and matched path.
     *
     * @return array<string, string>
     */
    public function extractParametersFromNormalizedPath(string $path): array
    {
        return $this->compiledPath->extractParametersFromNormalizedPath($path);
    }

    /**
     * Generates this route's path from route parameters.
     *
     * @param array<string, string|int|float> $parameters
     *
     * @throws UrlGenerationException
     */
    public function generatePath(array $parameters = []): string
    {
        return $this->compiledPath->generatePath($parameters);
    }

    /**
     * @param array{
     *     methods: list<string>,
     *     path: string,
     *     handler: string,
     *     middleware: list<string>,
     *     name: string|null,
     *     compiled_path: array{
     *         path: string,
     *         matching_regex: non-empty-string,
     *         parameters: list<array{
     *             name: string,
     *             placeholder: string,
     *             constraint: string|null,
     *             validationRegex: non-empty-string|null
     *         }>,
     *         static: bool,
     *         segment_count: int,
     *         first_literal_segment: string|null
     *     }
     * } $data
     */
    public static function fromExport(array $data): self
    {
        /** @var list<class-string<MiddlewareInterface>> $middleware */
        $middleware = $data['middleware'];

        return new self(
            $data['methods'],
            $data['path'],
            $data['handler'],
            $middleware,
            $data['name'],
            CompiledRoutePath::fromExport($data['compiled_path'])
        );
    }

    /**
     * @return array{
     *     methods: list<string>,
     *     path: string,
     *     handler: string,
     *     middleware: list<string>,
     *     name: string|null,
     *     compiled_path: array{
     *         path: string,
     *         matching_regex: non-empty-string,
     *         parameters: list<array{
     *             name: string,
     *             placeholder: string,
     *             constraint: string|null,
     *             validationRegex: non-empty-string|null
     *         }>,
     *         static: bool,
     *         segment_count: int,
     *         first_literal_segment: string|null
     *     }
     * }
     */
    public function export(): array
    {
        if (!is_string($this->handler)) {
            throw new InvalidArgumentException(sprintf(
                'Route [%s] cannot be exported because its handler is not a class-string.',
                $this->path
            ));
        }

        $middleware = [];

        foreach ($this->middleware as $definition) {
            if (!is_string($definition)) {
                throw new InvalidArgumentException(sprintf(
                    'Route [%s] cannot be exported because it contains instance-based middleware.',
                    $this->path
                ));
            }

            $middleware[] = $definition;
        }

        return [
            'methods' => $this->methods,
            'path' => $this->path,
            'handler' => $this->handler,
            'middleware' => $middleware,
            'name' => $this->name,
            'compiled_path' => $this->compiledPath->export(),
        ];
    }

    private static function normalizeName(string $name): string
    {
        $normalizedName = trim($name);

        if ($normalizedName === '') {
            throw new InvalidArgumentException('Route name must not be empty.');
        }

        return $normalizedName;
    }
}
