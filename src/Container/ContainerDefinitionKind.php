<?php

declare(strict_types=1);

namespace Framework\Container;

enum ContainerDefinitionKind: string
{
    case ClassString = 'class-string';
    case Callable = 'callable';
    case Object = 'object';
}
