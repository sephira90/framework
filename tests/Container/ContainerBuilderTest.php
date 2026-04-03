<?php

declare(strict_types=1);

namespace Framework\Tests\Container;

use DateTimeZone;
use Framework\Container\ContainerBuilder;
use Framework\Container\ContainerException;
use Framework\Tests\Support\FrameworkTestCase;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use stdClass;

/** @psalm-suppress UnusedClass */
final class ContainerBuilderTest extends FrameworkTestCase
{
    public function testBuilderRejectsUnknownClassStrings(): void
    {
        $builder = new ContainerBuilder();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('references unknown class');

        $builder->bind('missing.service', 'Framework\\Tests\\Container\\MissingService');
    }

    public function testBuilderRejectsConcreteObjectsForNonSharedBindings(): void
    {
        $builder = new ContainerBuilder();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot bind a concrete object as non-shared');

        $builder->bind('object.service', new stdClass());
    }

    public function testBuilderGeneratedContainerRejectsClassesWithRequiredConstructors(): void
    {
        $builder = new ContainerBuilder();
        $builder->singleton(DateTimeZone::class, DateTimeZone::class);

        $container = $builder->build();

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('needs an explicit factory');

        $container->get(DateTimeZone::class);
    }

    public function testBuilderAliasesCanPointToServicesRegisteredLater(): void
    {
        $builder = new ContainerBuilder();
        $builder->alias('alias.service', 'target.service');
        $builder->singleton('target.service', stdClass::class);

        $container = $builder->build();
        $resolved = $container->get('alias.service');

        self::assertTrue($container->has('alias.service'));
        self::assertInstanceOf(stdClass::class, $resolved);
        self::assertSame($resolved, $container->get('target.service'));
    }

    public function testBuilderPassesContainerToRequiredFactoryParameter(): void
    {
        $builder = new ContainerBuilder();
        $builder->singleton(
            'container.service',
            static fn (ContainerInterface $container): ContainerInterface => $container
        );

        $container = $builder->build();

        self::assertSame($container, $container->get('container.service'));
    }

    public function testBuilderPassesContainerToOptionalFactoryParameter(): void
    {
        $builder = new ContainerBuilder();
        $builder->singleton(
            'container.service',
            static fn (?ContainerInterface $container = null): ?ContainerInterface => $container
        );

        $container = $builder->build();

        self::assertSame($container, $container->get('container.service'));
    }

    public function testBuilderRejectsFactoriesWithMoreThanOneParameter(): void
    {
        $builder = new ContainerBuilder();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must accept zero or one ContainerInterface-compatible argument');

        $builder->bind(
            'invalid.factory',
            static function (mixed $first, mixed $second): stdClass {
                unset($first, $second);

                return new stdClass();
            }
        );
    }

    public function testBuilderRejectsFactoriesWithUntypedParameter(): void
    {
        $builder = new ContainerBuilder();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must accept zero or one ContainerInterface-compatible argument');

        $builder->bind(
            'invalid.factory',
            /** @psalm-suppress MissingClosureParamType */
            static function ($value): stdClass {
                unset($value);

                return new stdClass();
            }
        );
    }

    public function testBuilderRejectsFactoriesWithBuiltinParameter(): void
    {
        $builder = new ContainerBuilder();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must accept zero or one ContainerInterface-compatible argument');

        $builder->bind(
            'invalid.factory',
            static function (int $value): stdClass {
                unset($value);

                return new stdClass();
            }
        );
    }

    public function testBuilderRejectsFactoriesWithRequiredNonContainerObjectParameter(): void
    {
        $builder = new ContainerBuilder();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must accept zero or one ContainerInterface-compatible argument');

        $builder->bind(
            'invalid.factory',
            static function (stdClass $value): stdClass {
                return $value;
            }
        );
    }

    public function testBuilderRejectsFactoriesWithOptionalNonContainerObjectParameter(): void
    {
        $builder = new ContainerBuilder();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must accept zero or one ContainerInterface-compatible argument');

        $builder->bind(
            'invalid.factory',
            static function (?stdClass $value = null): stdClass {
                return $value ?? new stdClass();
            }
        );
    }

    public function testBuilderRejectsDuplicateDefinitionIdentifiers(): void
    {
        $builder = new ContainerBuilder();
        $builder->bind('duplicate.service', stdClass::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('conflicts with existing definition');

        $builder->singleton('duplicate.service', stdClass::class);
    }

    public function testBuilderRejectsDuplicateAliasIdentifiers(): void
    {
        $builder = new ContainerBuilder();
        $builder->alias('alias.service', 'first.target');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('conflicts with existing alias');

        $builder->alias('alias.service', 'second.target');
    }

    public function testBuilderRejectsAliasesThatReuseDefinitionIdentifiers(): void
    {
        $builder = new ContainerBuilder();
        $builder->singleton('shared.service', stdClass::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('conflicts with existing definition');

        $builder->alias('shared.service', 'target.service');
    }
}
