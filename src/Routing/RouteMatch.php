<?php

declare(strict_types=1);

namespace Framework\Routing;

use LogicException;

/**
 * Типизированный результат матчинга маршрута.
 *
 * Вместо `null` или ad-hoc массива router возвращает explicit result object,
 * который заставляет вызывающий код различать найденный маршрут, 404 и 405.
 */
final readonly class RouteMatch
{
    /**
     * @param array<string, string> $parameters
     * @param list<string> $allowedMethods
     */
    private function __construct(
        private RouteMatchStatus $status,
        private ?Route $route,
        private array $parameters,
        private array $allowedMethods,
    ) {
    }

    /**
     * @param array<string, string> $parameters
     */
    public static function found(Route $route, array $parameters): self
    {
        return new self(RouteMatchStatus::Found, $route, $parameters, []);
    }

    public static function notFound(): self
    {
        return new self(RouteMatchStatus::NotFound, null, [], []);
    }

    /**
     * @param list<string> $allowedMethods
     */
    public static function methodNotAllowed(array $allowedMethods): self
    {
        sort($allowedMethods);

        return new self(RouteMatchStatus::MethodNotAllowed, null, [], array_values(array_unique($allowedMethods)));
    }

    /**
     * Возвращает тип исхода матчинга.
     */
    public function status(): RouteMatchStatus
    {
        return $this->status;
    }

    /**
     * Возвращает matched route только для статуса Found.
     */
    public function route(): Route
    {
        if ($this->route === null) {
            throw new LogicException('Route is only available on a successful match.');
        }

        return $this->route;
    }

    /**
     * Возвращает route parameters для успешного матчинга.
     *
     * @return array<string, string>
     */
    public function parameters(): array
    {
        return $this->parameters;
    }

    /**
     * Возвращает список методов для `405 Method Not Allowed`.
     *
     * @return list<string>
     */
    public function allowedMethods(): array
    {
        return $this->allowedMethods;
    }
}
