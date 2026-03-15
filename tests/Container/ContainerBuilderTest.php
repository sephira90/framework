<?php

declare(strict_types=1);

namespace Framework\Tests\Container;

use DateTimeZone;
use Framework\Container\ContainerBuilder;
use Framework\Container\ContainerException;
use Framework\Tests\Support\FrameworkTestCase;
use InvalidArgumentException;
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

    public function testBuilderRejectsFactoriesWithMoreThanOneParameter(): void
    {
        $builder = new ContainerBuilder();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must accept zero or one ContainerInterface argument');

        $builder->bind(
            'invalid.factory',
            static function (mixed $first, mixed $second): stdClass {
                unset($first, $second);

                return new stdClass();
            }
        );
    }
}
