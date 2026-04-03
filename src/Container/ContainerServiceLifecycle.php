<?php

declare(strict_types=1);

namespace Framework\Container;

enum ContainerServiceLifecycle: string
{
    case Binding = 'binding';
    case Singleton = 'singleton';
}
