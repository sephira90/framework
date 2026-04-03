<?php

declare(strict_types=1);

namespace Framework\Tests\Support\Fixtures;

use Psr\Container\ContainerInterface;
use stdClass;

final class ContainerInspectionFactory
{
    /** @psalm-suppress PossiblyUnusedReturnValue */
    public static function makeObject(): stdClass
    {
        return new stdClass();
    }

    /** @psalm-suppress PossiblyUnusedReturnValue */
    public static function makeContainerAwareObject(ContainerInterface $container): stdClass
    {
        unset($container);

        return new stdClass();
    }
}
