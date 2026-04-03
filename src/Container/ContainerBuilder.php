<?php

declare(strict_types=1);

namespace Framework\Container;

use Closure;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionFunction;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Регистрационный слой для container definitions.
 *
 * Builder собирает service graph в форме, удобной для runtime container'а:
 * definitions и aliases. Здесь же нормализуются class-string/object/callable
 * значения, проверяются базовые ограничения и параллельно фиксируется
 * sidecar inspection snapshot registration graph'а.
 */
final class ContainerBuilder
{
    /** @var array<string, ServiceDefinition> */
    private array $definitions = [];

    /** @var array<string, string> */
    private array $aliases = [];

    /** @var array<string, ContainerDefinitionDescriptor> */
    private array $definitionDescriptors = [];

    /** @var array<string, ContainerAliasDescriptor> */
    private array $aliasDescriptors = [];

    /**
     * Регистрирует transient service.
     */
    public function bind(
        string $id,
        callable|string|object $concrete,
        ContainerEntryOwner $owner = ContainerEntryOwner::Application,
        string $origin = 'manual'
    ): void {
        $this->register($id, $concrete, false, $owner, $origin);
    }

    /**
     * Регистрирует shared service.
     */
    public function singleton(
        string $id,
        callable|string|object $concrete,
        ContainerEntryOwner $owner = ContainerEntryOwner::Application,
        string $origin = 'manual'
    ): void {
        $this->register($id, $concrete, true, $owner, $origin);
    }

    /**
     * Добавляет alias на уже существующий или будущий service id.
     */
    public function alias(
        string $id,
        string $target,
        ContainerEntryOwner $owner = ContainerEntryOwner::Application,
        string $origin = 'manual'
    ): void {
        $this->assertAliasIdAvailable($id, $owner, $origin);
        $this->aliases[$id] = $target;
        $this->aliasDescriptors[$id] = new ContainerAliasDescriptor($id, $target, $owner, $origin);
    }

    /**
     * Материализует runtime container.
     */
    public function build(): Container
    {
        return new Container($this->definitions, $this->aliases);
    }

    public function inspectionSnapshot(): ContainerInspectionSnapshot
    {
        return new ContainerInspectionSnapshot($this->definitionDescriptors, $this->aliasDescriptors);
    }

    /**
     * Сохраняет definition в нормализованном runtime-friendly виде.
     */
    private function register(
        string $id,
        callable|string|object $concrete,
        bool $shared,
        ContainerEntryOwner $owner,
        string $origin
    ): void {
        $this->assertDefinitionIdAvailable($id, $owner, $origin);

        [$definition, $descriptor] = $this->normalizeDefinition($id, $concrete, $shared, $owner, $origin);

        $this->definitions[$id] = $definition;
        $this->definitionDescriptors[$id] = $descriptor;
    }

    /**
     * Приводит definition к runtime-friendly representation и заранее валидирует
     * допустимую форму service factory.
     *
     * @return array{0: ServiceDefinition, 1: ContainerDefinitionDescriptor}
     */
    private function normalizeDefinition(
        string $id,
        callable|string|object $concrete,
        bool $shared,
        ContainerEntryOwner $owner,
        string $origin
    ): array {
        $lifecycle = $shared ? ContainerServiceLifecycle::Singleton : ContainerServiceLifecycle::Binding;

        if (is_string($concrete)) {
            if (!class_exists($concrete)) {
                throw new InvalidArgumentException(sprintf(
                    'Service [%s] references unknown class [%s].',
                    $id,
                    $concrete
                ));
            }

            return [
                new ServiceDefinition(
                    static fn (): object => self::instantiateClass($id, $concrete),
                    $shared,
                    false
                ),
                new ContainerDefinitionDescriptor(
                    $id,
                    $owner,
                    $origin,
                    $lifecycle,
                    ContainerDefinitionKind::ClassString,
                    $concrete,
                    false
                ),
            ];
        }

        if (is_object($concrete) && !is_callable($concrete)) {
            if (!$shared) {
                throw new InvalidArgumentException(sprintf(
                    'Service [%s] cannot bind a concrete object as non-shared. Use singleton() or a factory.',
                    $id
                ));
            }

            return [
                new ServiceDefinition(
                    static fn (): object => $concrete,
                    $shared,
                    false
                ),
                new ContainerDefinitionDescriptor(
                    $id,
                    $owner,
                    $origin,
                    $lifecycle,
                    ContainerDefinitionKind::Object,
                    $concrete::class,
                    false
                ),
            ];
        }

        $label = self::callableLabel($concrete);
        $factory = Closure::fromCallable($concrete);
        $requiresContainer = self::analyzeFactory($id, $factory);

        return [
            new ServiceDefinition(
                $factory,
                $shared,
                $requiresContainer
            ),
            new ContainerDefinitionDescriptor(
                $id,
                $owner,
                $origin,
                $lifecycle,
                ContainerDefinitionKind::Callable,
                $label,
                $requiresContainer
            ),
        ];
    }

    private function assertDefinitionIdAvailable(string $id, ContainerEntryOwner $owner, string $origin): void
    {
        if (isset($this->definitionDescriptors[$id])) {
            $existing = $this->definitionDescriptors[$id];

            throw new InvalidArgumentException(sprintf(
                'Definition [%s] from [%s] conflicts with existing definition from [%s].',
                $id,
                self::formatRegistrationSource($owner, $origin),
                self::formatRegistrationSource($existing->owner(), $existing->origin())
            ));
        }

        if (isset($this->aliasDescriptors[$id])) {
            $existing = $this->aliasDescriptors[$id];

            throw new InvalidArgumentException(sprintf(
                'Definition [%s] from [%s] conflicts with existing alias from [%s].',
                $id,
                self::formatRegistrationSource($owner, $origin),
                self::formatRegistrationSource($existing->owner(), $existing->origin())
            ));
        }
    }

    private function assertAliasIdAvailable(string $id, ContainerEntryOwner $owner, string $origin): void
    {
        if (isset($this->aliasDescriptors[$id])) {
            $existing = $this->aliasDescriptors[$id];

            throw new InvalidArgumentException(sprintf(
                'Alias [%s] from [%s] conflicts with existing alias from [%s].',
                $id,
                self::formatRegistrationSource($owner, $origin),
                self::formatRegistrationSource($existing->owner(), $existing->origin())
            ));
        }

        if (isset($this->definitionDescriptors[$id])) {
            $existing = $this->definitionDescriptors[$id];

            throw new InvalidArgumentException(sprintf(
                'Alias [%s] from [%s] conflicts with existing definition from [%s].',
                $id,
                self::formatRegistrationSource($owner, $origin),
                self::formatRegistrationSource($existing->owner(), $existing->origin())
            ));
        }
    }

    private static function analyzeFactory(string $id, Closure $factory): bool
    {
        $reflection = new ReflectionFunction($factory);
        $totalParameters = $reflection->getNumberOfParameters();

        if ($totalParameters > 1) {
            throw new InvalidArgumentException(sprintf(
                'Service [%s] factories must accept zero or one ContainerInterface-compatible argument.',
                $id
            ));
        }

        if ($totalParameters === 0) {
            return false;
        }

        $parameters = $reflection->getParameters();
        $parameter = $parameters[0] ?? null;

        if ($parameter === null) {
            throw new InvalidArgumentException(sprintf(
                'Service [%s] factories must accept zero or one ContainerInterface-compatible argument.',
                $id
            ));
        }

        if (!self::parameterAcceptsContainer($parameter)) {
            throw new InvalidArgumentException(sprintf(
                'Service [%s] factories must accept zero or one ContainerInterface-compatible argument.',
                $id
            ));
        }

        return true;
    }

    private static function parameterAcceptsContainer(ReflectionParameter $parameter): bool
    {
        $type = $parameter->getType();

        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return false;
        }

        return is_a($type->getName(), ContainerInterface::class, true);
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

    private static function callableLabel(callable|object $callable): string
    {
        if ($callable instanceof Closure) {
            return Closure::class;
        }

        if (is_array($callable)) {
            $target = $callable[0] ?? null;
            $method = $callable[1] ?? null;

            if ((is_string($target) || is_object($target)) && is_string($method)) {
                $label = is_object($target) ? $target::class : $target;

                return $label . '::' . $method;
            }
        }

        if (is_object($callable)) {
            return $callable::class;
        }

        return 'callable';
    }

    private static function formatRegistrationSource(ContainerEntryOwner $owner, string $origin): string
    {
        return $owner->value . ':' . $origin;
    }
}
