<?php

declare(strict_types=1);

namespace Framework\Tests\Foundation\Bootstrap;

use ArrayObject;
use Framework\Config\Config;
use Framework\Foundation\Bootstrap\BootableProviderInterface;
use Framework\Foundation\Bootstrap\BootstrapBuilder;
use Framework\Foundation\Bootstrap\BootstrapContext;
use Framework\Foundation\Bootstrap\Bootstrapper;
use Framework\Foundation\Bootstrap\ServiceProviderInterface;
use Framework\Tests\Support\FrameworkTestCase;
use Override;
use stdClass;

/** @psalm-suppress UnusedClass */
final class BootstrapperTest extends FrameworkTestCase
{
    public function testBootstrapperRegistersAllProvidersBeforeBootingOnlyBootableProvidersInOrder(): void
    {
        /** @var ArrayObject<int, string> $events */
        $events = new ArrayObject();
        $markerId = 'bootstrap.marker';

        $providers = [
            new class ($events, $markerId) implements ServiceProviderInterface, BootableProviderInterface {
                /**
                 * @param ArrayObject<int, string> $events
                 */
                public function __construct(
                    private ArrayObject $events,
                    private string $markerId,
                ) {
                }

                #[Override]
                public function register(BootstrapBuilder $builder): void
                {
                    $this->events->append('register:first:' . $builder->basePath());
                    $builder->containerBuilder()->singleton($this->markerId, new stdClass());
                }

                #[Override]
                public function boot(BootstrapContext $context): void
                {
                    $this->events->append(
                        'boot:first:' . ($context->container()->has($this->markerId) ? 'built' : 'missing')
                    );
                }
            },
            new class ($events) implements ServiceProviderInterface {
                /**
                 * @param ArrayObject<int, string> $events
                 */
                public function __construct(
                    private ArrayObject $events,
                ) {
                }

                #[Override]
                public function register(BootstrapBuilder $builder): void
                {
                    $this->events->append('register:second:' . $builder->basePath());
                }
            },
            new class ($events, $markerId) implements ServiceProviderInterface, BootableProviderInterface {
                /**
                 * @param ArrayObject<int, string> $events
                 */
                public function __construct(
                    private ArrayObject $events,
                    private string $markerId,
                ) {
                }

                #[Override]
                public function register(BootstrapBuilder $builder): void
                {
                    $this->events->append('register:third:' . $builder->basePath());
                }

                #[Override]
                public function boot(BootstrapContext $context): void
                {
                    $this->events->append(
                        'boot:third:' . ($context->container()->has($this->markerId) ? 'built' : 'missing')
                    );
                }
            },
        ];

        $bootstrapper = new Bootstrapper($providers);
        $container = $bootstrapper->bootstrap('C:\\bootstrap-test', new Config([]));

        self::assertTrue($container->has($markerId));
        self::assertSame([
            'register:first:C:\\bootstrap-test',
            'register:second:C:\\bootstrap-test',
            'register:third:C:\\bootstrap-test',
            'boot:first:built',
            'boot:third:built',
        ], $events->getArrayCopy());
    }
}
