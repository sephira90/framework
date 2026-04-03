<?php

declare(strict_types=1);

namespace Framework\Tests\Container;

use Framework\Container\ContainerBuilder;
use Framework\Container\ContainerDefinitionKind;
use Framework\Container\ContainerEntryOwner;
use Framework\Container\ContainerServiceLifecycle;
use Framework\Tests\Support\Fixtures\ContainerInspectionFactory;
use Framework\Tests\Support\FrameworkTestCase;
use stdClass;

/** @psalm-suppress UnusedClass */
final class ContainerInspectionTest extends FrameworkTestCase
{
    public function testInspectionSnapshotCapturesDefinitionAndAliasDescriptors(): void
    {
        $builder = new ContainerBuilder();
        $object = new stdClass();

        $builder->bind(
            'binding.service',
            stdClass::class,
            ContainerEntryOwner::Application,
            'container.bindings'
        );
        $builder->singleton(
            'callable.service',
            [ContainerInspectionFactory::class, 'makeObject'],
            ContainerEntryOwner::Application,
            'container.singletons'
        );
        $builder->singleton(
            'container.callable',
            [ContainerInspectionFactory::class, 'makeContainerAwareObject'],
            ContainerEntryOwner::Application,
            'container.singletons'
        );
        $builder->singleton(
            'object.service',
            $object,
            ContainerEntryOwner::Framework,
            'Framework\\Tests\\Container\\ProviderProbe'
        );
        $builder->alias(
            'alias.service',
            'binding.service',
            ContainerEntryOwner::Application,
            'container.aliases'
        );

        $snapshot = $builder->inspectionSnapshot();
        $binding = $snapshot->definition('binding.service');
        $callable = $snapshot->definition('callable.service');
        $containerAware = $snapshot->definition('container.callable');
        $singletonObject = $snapshot->definition('object.service');
        $alias = $snapshot->alias('alias.service');

        self::assertNotNull($binding);
        self::assertSame(ContainerEntryOwner::Application, $binding->owner());
        self::assertSame('container.bindings', $binding->origin());
        self::assertSame(ContainerServiceLifecycle::Binding, $binding->lifecycle());
        self::assertSame(ContainerDefinitionKind::ClassString, $binding->definitionKind());
        self::assertSame(stdClass::class, $binding->label());
        self::assertFalse($binding->requiresContainer());

        self::assertNotNull($callable);
        self::assertSame(ContainerDefinitionKind::Callable, $callable->definitionKind());
        self::assertSame(
            ContainerInspectionFactory::class . '::makeObject',
            $callable->label()
        );
        self::assertFalse($callable->requiresContainer());

        self::assertNotNull($containerAware);
        self::assertSame(ContainerDefinitionKind::Callable, $containerAware->definitionKind());
        self::assertSame(
            ContainerInspectionFactory::class . '::makeContainerAwareObject',
            $containerAware->label()
        );
        self::assertTrue($containerAware->requiresContainer());

        self::assertNotNull($singletonObject);
        self::assertSame(ContainerEntryOwner::Framework, $singletonObject->owner());
        self::assertSame(ContainerServiceLifecycle::Singleton, $singletonObject->lifecycle());
        self::assertSame(ContainerDefinitionKind::Object, $singletonObject->definitionKind());
        self::assertSame(stdClass::class, $singletonObject->label());
        self::assertFalse($singletonObject->requiresContainer());

        self::assertNotNull($alias);
        self::assertSame(ContainerEntryOwner::Application, $alias->owner());
        self::assertSame('container.aliases', $alias->origin());
        self::assertSame('binding.service', $alias->target());
    }
}
