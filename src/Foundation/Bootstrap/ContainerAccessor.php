<?php

declare(strict_types=1);

namespace Framework\Foundation\Bootstrap;

use InvalidArgumentException;
use Psr\Container\ContainerInterface;

/**
 * Typed helper for resolving object services from the container.
 */
final class ContainerAccessor
{
    /**
     * @template T of object
     *
     * @param class-string<T> $id
     * @return T
     */
    public static function get(ContainerInterface $container, string $id): object
    {
        $service = $container->get($id);

        if (!is_object($service)) {
            throw new InvalidArgumentException(sprintf(
                'Service [%s] must resolve to an object, got [%s].',
                $id,
                gettype($service)
            ));
        }

        /** @var T $service */
        return $service;
    }
}
