<?php

declare(strict_types=1);

namespace Framework\Console\Internal;

use Framework\Config\ProjectConfigLoader;
use Framework\Console\CommandInput;
use Framework\Console\CommandInterface;
use Framework\Console\ConsoleOutput;
use Framework\Foundation\Bootstrap\RoutesFileLoader;
use Framework\Routing\Route;
use Override;

/**
 * Prints the current route table in registration order for observability.
 */
final readonly class RouteListCommand implements CommandInterface
{
    public function __construct(
        private string $basePath,
        private ProjectConfigLoader $configLoader = new ProjectConfigLoader(),
        private RoutesFileLoader $routesLoader = new RoutesFileLoader(),
    ) {
    }

    #[Override]
    public function execute(CommandInput $input, ConsoleOutput $output): int
    {
        $config = $input->option('source', false) === true
            ? $this->configLoader->loadSource($this->basePath)
            : $this->configLoader->loadRuntime($this->basePath);
        $routes = $input->option('source', false) === true
            ? $this->routesLoader->loadSource($this->basePath, $config)
            : $this->routesLoader->load($this->basePath, $config);

        $output->writeln('METHODS	PATH	NAME	HANDLER	MIDDLEWARE');

        foreach ($routes->routes() as $route) {
            $output->writeln(sprintf(
                '%s	%s	%s	%s	%d',
                implode(',', $route->effectiveMethods()),
                $route->path(),
                $route->name() ?? '-',
                $this->handlerLabel($route),
                count($route->middleware())
            ));
        }

        return 0;
    }

    private function handlerLabel(Route $route): string
    {
        $handler = $route->handler();

        return is_string($handler) ? $handler : 'Closure';
    }
}
