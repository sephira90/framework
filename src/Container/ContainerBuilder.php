<?php

declare(strict_types=1);

namespace Framework\Container;

use Closure;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionFunction;

/**
 * Регистрационный слой для container definitions.
 *
 * Builder собирает service graph в форме, удобной для runtime container'а:
 * definitions и aliases. Здесь же нормализуются class-string/object/callable
 * значения и проверяются базовые ограничения.
 */
final class ContainerBuilder
{
    /** @var array<string, ServiceDefinition> */
    private array $definitions = [];

    /** @var array<string, string> */
    private array $aliases = [];

    /**
     * Регистрирует transient service.
     */
    public function bind(string $id, callable|string|object $concrete): void
    {
        $this->register($id, $concrete, false);
    }

    /**
     * Регистрирует shared service.
     */
    public function singleton(string $id, callable|string|object $concrete): void
    {
        $this->register($id, $concrete, true);
    }

    /**
     * Добавляет alias на уже существующий или будущий service id.
     */
    public function alias(string $id, string $target): void
    {
        $this->aliases[$id] = $target;
    }

    /**
     * Материализует runtime container.
     */
    public function build(): Container
    {
        return new Container($this->definitions, $this->aliases);
    }

    /**
     * Сохраняет definition в нормализованном runtime-friendly виде.
     */
    private function register(string $id, callable|string|object $concrete, bool $shared): void
    {
        $this->definitions[$id] = $this->normalizeDefinition($id, $concrete, $shared);
    }

    /**
     * Приводит definition к runtime-friendly representation и заранее валидирует
     * допустимую форму service factory.
     */
    private function normalizeDefinition(string $id, callable|string|object $concrete, bool $shared): ServiceDefinition
    {
        if (is_string($concrete)) {
            if (!class_exists($concrete)) {
                throw new InvalidArgumentException(sprintf(
                    'Service [%s] references unknown class [%s].',
                    $id,
                    $concrete
                ));
            }

            return new ServiceDefinition(
                static fn (): object => self::instantiateClass($id, $concrete),
                $shared,
                false
            );
        }

        if (is_object($concrete) && !is_callable($concrete)) {
            if (!$shared) {
                throw new InvalidArgumentException(sprintf(
                    'Service [%s] cannot bind a concrete object as non-shared. Use singleton() or a factory.',
                    $id
                ));
            }

            return new ServiceDefinition(
                static fn (): object => $concrete,
                $shared,
                false
            );
        }

        $factory = Closure::fromCallable($concrete);

        return new ServiceDefinition(
            $factory,
            $shared,
            self::factoryRequiresContainer($id, $factory)
        );
    }

    private static function factoryRequiresContainer(string $id, Closure $factory): bool
    {
        $reflection = new ReflectionFunction($factory);
        $requiredParameters = $reflection->getNumberOfRequiredParameters();
        $totalParameters = $reflection->getNumberOfParameters();

        if ($requiredParameters > 1 || $totalParameters > 1) {
            throw new InvalidArgumentException(sprintf(
                'Service [%s] factories must accept zero or one ContainerInterface argument.',
                $id
            ));
        }

        return $requiredParameters === 1;
    }

    /**
     * Создаёт zero-argument class service без autowiring.
     *
     * @param class-string $class
     */
    private static function instantiateClass(string $id, string $class): object
    {
        $reflection = new ReflectionClass($class);

        $constructor = $reflection->getConstructor();

        if ($constructor !== null && $constructor->getNumberOfRequiredParameters() > 0) {
            throw new InvalidArgumentException(sprintf(
                'Service [%s] needs an explicit factory because class [%s] has required constructor arguments.',
                $id,
                $class
            ));
        }

        return $reflection->newInstance();
    }
}
