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

    private string $path;

    private CompiledRoutePath $compiledPath;

    private Closure|string $handler;

    private ?string $name;

    private bool $static;

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
    ) {
        $normalizedMethods = array_values(array_unique(array_map(
            static fn (string $method): string => strtoupper($method),
            $methods
        )));

        if ($normalizedMethods === []) {
            throw new InvalidArgumentException('Route must define at least one HTTP method.');
        }

        $this->methods = $normalizedMethods;
        $this->path = self::normalizePath($path);
        $this->compiledPath = CompiledRoutePath::fromNormalizedPath($this->path);
        $this->handler = is_string($handler) ? $handler : Closure::fromCallable($handler);
        $this->name = $name !== null ? self::normalizeName($name) : null;
        $this->static = $this->compiledPath->isStatic();
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

    private static function normalizeName(string $name): string
    {
        $normalizedName = trim($name);

        if ($normalizedName === '') {
            throw new InvalidArgumentException('Route name must not be empty.');
        }

        return $normalizedName;
    }
}
