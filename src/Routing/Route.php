<?php

declare(strict_types=1);

namespace Framework\Routing;

use Closure;
use InvalidArgumentException;
use Psr\Http\Server\MiddlewareInterface;

/**
 * Маршрут HTTP-слоя.
 *
 * Route хранит:
 *
 * - допустимые HTTP methods;
 * - нормализованный path;
 * - optional route name для URL generation;
 * - handler definition;
 * - route-level middleware;
 * - предвычисленную форму для matching и parameter extraction.
 *
 * Для v0 это один концепт и одна единица сопровождения: данные маршрута и его
 * matching invariants расположены рядом.
 *
 * Нормализация внешнего path выполняется на boundary routing layer. После этого
 * Route ожидает уже канонический path и не повторяет ту же работу внутри
 * matching helpers.
 */
final readonly class Route
{
    /** @var list<string> */
    private array $methods;

    private string $path;

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
        $this->handler = is_string($handler) ? $handler : Closure::fromCallable($handler);
        $this->name = $name !== null ? self::normalizeName($name) : null;
        [$this->regex, $this->parameterNames, $this->static] = self::compilePath($this->path);
    }

    private Closure|string $handler;

    private ?string $name;

    /** @var non-empty-string */
    private string $regex;

    /** @var list<string> */
    private array $parameterNames;

    private bool $static;

    /**
     * Приводит path к единому каноническому виду, чтобы routing не зависел от
     * лишних слэшей и случайной формы ввода.
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
     * Возвращает новый route instance с именем для URL generation.
     */
    public function withName(string $name): self
    {
        return new self($this->methods, $this->path, $this->handler, $this->middleware, $name);
    }

    /**
     * Проверяет, подходит ли уже нормализованный path под этот маршрут.
     *
     * Router владеет нормализацией пользовательского ввода и передаёт сюда
     * канонический path. Это удерживает правило "normalize once at the boundary"
     * вместо повторной defensive normalization в каждом helper method.
     */
    public function matchesNormalizedPath(string $path): bool
    {
        if ($this->static) {
            return $this->path === $path;
        }

        return (bool) preg_match($this->regex, $path);
    }

    /**
     * Извлекает route parameters из уже нормализованного и сматченного path.
     *
     * @return array<string, string>
     */
    public function extractParametersFromNormalizedPath(string $path): array
    {
        if ($this->static) {
            return [];
        }

        $matches = [];

        if (!preg_match($this->regex, $path, $matches)) {
            return [];
        }

        $parameters = [];

        foreach ($this->parameterNames as $parameterName) {
            $parameters[$parameterName] = rawurldecode($matches[$parameterName] ?? '');
        }

        return $parameters;
    }

    /**
     * Генерирует path этого маршрута из route parameters.
     *
     * @param array<string, string|int|float> $parameters
     */
    public function generatePath(array $parameters = []): string
    {
        if ($this->parameterNames === []) {
            return $this->path;
        }

        $path = $this->path;

        foreach ($this->parameterNames as $parameterName) {
            if (!array_key_exists($parameterName, $parameters)) {
                throw new InvalidArgumentException(sprintf(
                    'Route [%s] requires parameter [%s] for URL generation.',
                    $this->path,
                    $parameterName
                ));
            }

            $path = str_replace(
                '{' . $parameterName . '}',
                rawurlencode((string) $parameters[$parameterName]),
                $path
            );
        }

        return $path;
    }

    /**
     * Компилирует route path в regex и фиксирует список параметров.
     *
     * @return array{0: non-empty-string, 1: list<string>, 2: bool}
     */
    private static function compilePath(string $path): array
    {
        if ($path === '/') {
            return ['#^/$#', [], true];
        }

        $segments = explode('/', trim($path, '/'));
        $parameterNames = [];
        $patternSegments = [];

        foreach ($segments as $segment) {
            if (preg_match('/^\{([A-Za-z_][A-Za-z0-9_]*)\}$/', $segment, $matches) === 1) {
                $parameterNames[] = $matches[1];
                $patternSegments[] = sprintf('(?P<%s>[^/]+)', $matches[1]);

                continue;
            }

            $patternSegments[] = preg_quote($segment, '#');
        }

        if ($parameterNames === []) {
            return ['#^' . preg_quote($path, '#') . '$#', [], true];
        }

        if (count(array_unique($parameterNames)) !== count($parameterNames)) {
            throw new InvalidArgumentException(sprintf('Route path [%s] contains duplicate parameter names.', $path));
        }

        return ['#^/' . implode('/', $patternSegments) . '$#', $parameterNames, false];
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
