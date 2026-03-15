<?php

declare(strict_types=1);

namespace Framework\Tests\Container;

use Framework\Container\ContainerBuilder;
use Framework\Container\ContainerException;
use Framework\Container\NotFoundException;
use Framework\Tests\Support\FrameworkTestCase;
use Psr\Container\ContainerInterface;
use stdClass;

/** @psalm-suppress UnusedClass */
final class ContainerTest extends FrameworkTestCase
{
    public function testContainerResolvesBindingsSingletonsAndAliases(): void
    {
        $builder = new ContainerBuilder();
        $bindingCounter = 0;
        $singletonCounter = 0;

        $builder->bind('binding', static function () use (&$bindingCounter): stdClass {
            $bindingCounter++;

            return new stdClass();
        });
        $builder->singleton('singleton', static function () use (&$singletonCounter): stdClass {
            $singletonCounter++;

            return new stdClass();
        });
        $builder->alias('alias.singleton', 'singleton');
        $builder->singleton(stdClass::class, stdClass::class);

        $container = $builder->build();

        self::assertTrue($container->has('binding'));
        self::assertTrue($container->has('alias.singleton'));
        self::assertFalse($container->has('missing'));

        self::assertNotSame($container->get('binding'), $container->get('binding'));
        self::assertSame($container->get('singleton'), $container->get('singleton'));
        self::assertSame($container->get('singleton'), $container->get('alias.singleton'));
        self::assertInstanceOf(stdClass::class, $container->get(stdClass::class));
        self::assertSame(2, $bindingCounter);
        self::assertSame(1, $singletonCounter);
    }

    public function testContainerThrowsForUnknownService(): void
    {
        $container = (new ContainerBuilder())->build();

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Service [missing] is not registered.');

        $container->get('missing');
    }

    public function testContainerDetectsCircularDependencies(): void
    {
        $builder = new ContainerBuilder();
        $builder->bind('service.a', static fn (ContainerInterface $container): mixed => $container->get('service.b'));
        $builder->bind('service.b', static fn (ContainerInterface $container): mixed => $container->get('service.a'));

        $container = $builder->build();

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Circular dependency detected');

        $container->get('service.a');
    }
}
