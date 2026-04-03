# Framework

Минимальный PHP framework core, который разрабатывается как учебно-инженерная система, а не как декоративный набор компонентов.

Проект решает две связанные задачи:

- собрать архитектурно честное framework core;
- использовать его разработку как среду для понимания HTTP, CLI, DI, routing, bootstrap lifecycle и инженерных trade-offs.

## Что есть сейчас

Текущее ядро уже не HTTP-only. Оно состоит из shared bootstrap layer и двух runtime axes:

- HTTP runtime:
  - `public/index.php`
  - `bootstrap/app.php`
  - `ApplicationFactory`
  - `HttpRuntime`
- Console runtime:
  - `bin/console`
  - `bootstrap/console.php`
  - `ConsoleApplicationFactory`
  - `ConsoleRuntime`

Поверх shared core уже реализованы:

- `.env` bootstrap и multi-file config с environment overlays;
- explicit `config:cache`, `route:cache`, `cache:clear` pipeline через `var/cache/framework/`;
- read-only observability commands:
  - `config:show [path] [--source]`
  - `route:list [--source]`
  - `container:debug [id] [--source]`
- explicit DI container без autowiring и без silent overrides framework-owned ids;
- HTTP routing с precompiled `RouteIndex`, static/dynamic routes, named routes, route groups и URL generation;
- global и route-level middleware;
- единый HTTP error boundary с controlled `HttpException` hierarchy;
- minimal CLI kernel с explicit command registration через `commands/console.php` и встроенными framework cache commands;
- deterministic CLI parser:
  - `command`
  - positional args
  - `--flag`
  - `--key=value`
  - `--`

## Что сознательно не входит в текущее ядро

- ORM и persistence layer;
- template/view layer;
- event system;
- queues и scheduler;
- session/auth/csrf stack;
- autowiring by default;
- public provider layer;
- advanced console UX:
  - short options;
  - option DSL;
  - interactive prompts;
  - ANSI styling;
  - command autodiscovery.

Отсутствие этих пунктов сейчас считается не дефектом, а границей системы.

## Структура репозитория

- `src/Config/` — конфигурация и окружение
- `src/Container/` — explicit DI container
- `src/Routing/` — маршруты, groups, names, URL generation, matching
- `src/Http/` — HTTP dispatch, resolvers, emitter, error responses, HTTP exceptions
- `src/Console/` — CLI input, command registration, command execution, console errors
- `src/Foundation/` — runtime factories, runtime snapshots и bootstrap orchestration
- `src/Foundation/Bootstrap/` — fixed internal provider lifecycle и boot state
- `app/Http/` — прикладные HTTP handlers
- `app/Console/` — прикладные CLI commands
- `config/` — config slices и environment overlays
- `routes/` — HTTP route registration
- `commands/` — CLI command registration
- `bootstrap/` — bootstrap files для HTTP и CLI
- `public/` — HTTP front controller
- `bin/` — CLI entrypoints
- `tests/` — regression safety net
- `docs/` — обзорная архитектурная документация
- `artifacts/execution/` — протокол инженерных шагов

## Быстрые entrypoints

- HTTP: [`public/index.php`](/C:/OSPanel/home/framework.ru/public/index.php)
- CLI: [`bin/console`](/C:/OSPanel/home/framework.ru/bin/console)
- Example command: `php bin/console app:about`
- Cache commands:
  - `php bin/console config:cache`
  - `php bin/console config:show`
  - `php bin/console route:cache`
  - `php bin/console route:list`
  - `php bin/console cache:clear`
  - `php bin/console container:debug`
- Recovery note:
  - `cache:clear` намеренно стартует через source config path, чтобы оставаться recovery-командой даже при несовместимом `config.php` cache snapshot
- Perf harness: `php tools/perf/benchmark.php [bootstrap|registration|routing|all]`

## Как читать проект

Если цель — понять систему, а не просто посмотреть файлы, идти лучше так:

1. [`docs/framework-architecture.md`](/C:/OSPanel/home/framework.ru/docs/framework-architecture.md)
2. Shared bootstrap:
   - [`src/Foundation/ApplicationFactory.php`](/C:/OSPanel/home/framework.ru/src/Foundation/ApplicationFactory.php)
   - [`src/Foundation/ConsoleApplicationFactory.php`](/C:/OSPanel/home/framework.ru/src/Foundation/ConsoleApplicationFactory.php)
   - [`src/Foundation/Bootstrap/Bootstrapper.php`](/C:/OSPanel/home/framework.ru/src/Foundation/Bootstrap/Bootstrapper.php)
   - [`src/Config/ProjectConfigLoader.php`](/C:/OSPanel/home/framework.ru/src/Config/ProjectConfigLoader.php)
3. HTTP path:
   - [`public/index.php`](/C:/OSPanel/home/framework.ru/public/index.php)
   - [`bootstrap/app.php`](/C:/OSPanel/home/framework.ru/bootstrap/app.php)
   - [`src/Foundation/Application.php`](/C:/OSPanel/home/framework.ru/src/Foundation/Application.php)
   - [`src/Http/RouteDispatcher.php`](/C:/OSPanel/home/framework.ru/src/Http/RouteDispatcher.php)
   - [`src/Routing/Router.php`](/C:/OSPanel/home/framework.ru/src/Routing/Router.php)
   - [`src/Routing/RouteIndex.php`](/C:/OSPanel/home/framework.ru/src/Routing/RouteIndex.php)
4. CLI path:
   - [`bin/console`](/C:/OSPanel/home/framework.ru/bin/console)
   - [`bootstrap/console.php`](/C:/OSPanel/home/framework.ru/bootstrap/console.php)
   - [`src/Console/ConsoleApplication.php`](/C:/OSPanel/home/framework.ru/src/Console/ConsoleApplication.php)
   - [`src/Console/ArgvInputFactory.php`](/C:/OSPanel/home/framework.ru/src/Console/ArgvInputFactory.php)
   - [`commands/console.php`](/C:/OSPanel/home/framework.ru/commands/console.php)
   - [`src/Console/Internal/ConfigCacheCommand.php`](/C:/OSPanel/home/framework.ru/src/Console/Internal/ConfigCacheCommand.php)
5. Калибровка через tests:
   - [`tests/Foundation/ApplicationFactoryTest.php`](/C:/OSPanel/home/framework.ru/tests/Foundation/ApplicationFactoryTest.php)
   - [`tests/Foundation/ConsoleApplicationFactoryTest.php`](/C:/OSPanel/home/framework.ru/tests/Foundation/ConsoleApplicationFactoryTest.php)

## Валидация

Проект калибруется через:

- `composer qa`
- `composer test`

`composer qa` включает:

- `php -l`
- `phpcs`
- `phpstan` level `10`
- `psalm`

## Текущее состояние

На момент текущей синхронизации baseline такой:

- `composer qa` — green
- `composer test` — green
- `144 tests / 534 assertions`
