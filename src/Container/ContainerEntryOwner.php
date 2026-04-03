<?php

declare(strict_types=1);

namespace Framework\Container;

enum ContainerEntryOwner: string
{
    case Framework = 'framework';
    case Application = 'application';
}
