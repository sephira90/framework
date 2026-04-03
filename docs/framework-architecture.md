# Архитектура Framework Core

## 1. Назначение документа

Этот документ объясняет текущую архитектуру framework core как системы:

- где проходят границы;
- что является shared bootstrap;
- как разделены HTTP и CLI runtime axes;
- какие инварианты защищает каждая подсистема;
- где система сознательно узкая;
- как её читать и развивать без магии.

Цель документа не перечислить файлы, а дать модель, по которой можно самостоятельно реконструировать поведение фреймворка.

---

## 2. Что представляет собой текущий framework

Текущий framework — это минимальный synchronous core с двумя execution axes:

- HTTP runtime;
- Console runtime.

Они делят:

- `.env` bootstrap;
- configuration model;
- explicit container;
- internal bootstrap lifecycle.

Они не делят runtime-specific services:

- HTTP path имеет request factory, router, middleware pipeline, response emitter;
- CLI path имеет argv parser, command registry, command resolver, console output и console error boundary.

Это не full-stack framework. Это deliberately narrow core, который показывает:

- как собирается runtime;
- как поднимается конфигурация;
- как описываются зависимости;
- как HTTP и CLI получают разные execution models поверх одного bootstrap layer.

---

## 3. Системные границы

### Что входит в ядро

- bootstrap окружения;
- immutable config repository;
- explicit DI container;
- internal provider lifecycle;
- HTTP routing и HTTP execution path;
- CLI command registration и CLI execution path;
- error boundaries для HTTP и CLI.

### Что сознательно не входит в ядро

- ORM и persistence layer;
- template/view system;
- events;
- queues и scheduler;
- session/auth/csrf stack;
- public provider API;
- autowiring by default;
- advanced console UX:
  - short options;
  - interactive prompts;
  - ANSI styling;
  - autodiscovery.

Эти ограничения важны. Иначе легко принять deliberately narrow design за «недоделанность», хотя это осознанная граница модели.

---

## 4. Карта каталогов

### `src/Config`

Отвечает за конфигурацию и окружение.

- `Config` — immutable repository поверх config array
- `ConfigLoader` — загружает `config/` directory и детерминированно мерджит config slices
- `ProjectConfigLoader` — решает, читать ли config из source files или из explicit cache snapshot
- `ConfigCacheCompiler` — строит exportable config snapshot для `var/cache/framework/config.php`
- `Env` — читает переменные окружения
- `EnvironmentLoader` — поднимает `.env` до сборки runtime
- `InvalidConfigurationException` — сигнал о плохой конфигурации

### `src/Container`

Отвечает за explicit dependency graph.

- `ContainerBuilder` — регистрирует bindings, singletons и aliases
- `ContainerInspectionSnapshot` — sidecar inspection model для observable registration graph без service resolution
- `ContainerDefinitionDescriptor` / `ContainerAliasDescriptor` — typed descriptors container inspection layer
- `ContainerEntryOwner` / `ContainerServiceLifecycle` / `ContainerDefinitionKind` — компактные enums observable container contract
- `Container` — резолвит сервисы
- `ServiceDefinition` — хранит factory, shared semantics и precomputed invocation mode
- `ContainerException` / `NotFoundException` — ошибки контейнера

### `src/Routing`

Отвечает за HTTP route model.

- `Route` — immutable описание маршрута
- `CompiledRoutePath` — compiled route-template contract for matching, parameter extraction and URL generation
- `RouteCollection` — ordered collection маршрутов
- `RouteCollector` — registration API
- `RouteBuilder` — fluent metadata seam
- `RouteIndex` — precompiled index для matching hot path и route cache
- `RouteCacheCompiler` — строит exportable route index для `var/cache/framework/routes.php`
- `Router` — thin facade поверх `RouteIndex` для matching и URL generation
- `RouteMatch` / `RouteMatchStatus` — typed result matching
- `RouteAttributes` — request attribute names для matched route и params

### `src/Http`

Отвечает за HTTP execution path.

- `RequestFactory` — создаёт `ServerRequestInterface` из globals
- `HandlerResolver` — превращает handler definition в исполнимый handler
- `MiddlewareResolver` — превращает middleware definition в `MiddlewareInterface`
- `MiddlewareDispatcher` — строит pipeline
- `RouteDispatcher` — связывает router, route attributes и route middleware
- `RouteHandler` — adapter между `Route` и handler resolution
- `ResponseEmitter` — отправляет PSR-7 response в SAPI
- `ErrorResponseFactory` — строит стандартные error responses
- `Http\\Middleware\\ErrorHandlingMiddleware` — outer HTTP error boundary
- `Http\\Exception\\*` — invalid definitions и controlled HTTP exceptions

### `src/Console`

Отвечает за CLI execution path.

- `ArgvInputFactory` — превращает raw `argv` в deterministic `CommandInput`
- `CommandInput` — immutable snapshot CLI input
- `CommandCollection` — validated collection зарегистрированных commands
- `CommandCollector` — registration seam для `commands/console.php`
- `CommandRegistry` — single-assignment boot state для commands
- `CommandResolver` — резолвит class command через контейнер
- `ConsoleApplication` — top-level CLI kernel
- `ConsoleOutput` — testable adapter над stdout/stderr
- `ConsoleErrorRenderer` — boundary-level CLI error rendering
- `CommandInterface` — контракт app command
- `Console\\Internal\\ConfigShowCommand` / `RouteListCommand` / `ContainerDebugCommand` — read-only observability commands для runtime/source views и container inspection

### `src/Foundation`

Отвечает за верхнеуровневую сборку runtime.

- `ApplicationFactory` — собирает HTTP runtime
- `ConsoleApplicationFactory` — собирает CLI runtime
- `Application` — top-level HTTP kernel
- `HttpRuntime` — snapshot готового HTTP runtime
- `ConsoleRuntime` — snapshot готового CLI runtime

### `src/Foundation/Bootstrap`

Отвечает за shared internal bootstrap lifecycle.

- `Bootstrapper` — прогоняет fixed-order `register -> build -> boot`
- `ServiceProviderInterface` — register contract
- `BootableProviderInterface` — optional boot contract
- `BootstrapBuilder` — pre-container context
- `BootstrapContext` — post-container context c `ContainerInterface`, а не concrete container
- `ContainerAccessor` — typed accessor поверх `ContainerInterface::get()`
- `FrameworkCachePaths` / `CacheFileWriter` — explicit cache path и cache persistence helpers
- `RouteRegistry` / `GlobalMiddlewareRegistry` / `CommandRegistry` — dedicated boot state
- `RoutesFileLoader` / `CommandsFileLoader` — scope-isolated project-bound app registration loaders
- `Provider\\*` — fixed internal providers

### Прикладной слой

- `app/Http/` — прикладные HTTP handlers
- `app/Console/` — прикладные CLI commands
- `routes/` — HTTP route registration
- `commands/` — CLI command registration
- `config/` — app config slices и overlays
- `bootstrap/` — bootstrap files для HTTP и CLI
- `public/` — HTTP entrypoint
- `bin/` — CLI entrypoint

---

## 5. Shared Bootstrap Model

Это центральный слой причинности. HTTP и CLI разные снаружи, но собираются через одну и ту же модель bootstrap.

### Entry points

- HTTP: `public/index.php -> bootstrap/app.php -> ApplicationFactory::createRuntime()`
- CLI: `bin/console -> bootstrap/console.php -> ConsoleApplicationFactory::createRuntime()`

### Шаг 1. Environment bootstrap

Обе factory делегируют чтение config `ProjectConfigLoader`, а он поднимает `.env` до source-config loading и до проверки cache snapshot.

Инвариант:

- `.env` поднимается до чтения `config/`;
- loader stateless и не хранит process-level cache путей;
- повторный bootstrap того же `basePath` зависит от текущего состояния окружения, а не от истории процесса.

### Шаг 2. Config assembly

Обе factory вызывают `ProjectConfigLoader`.

Инвариант:

- если существует `var/cache/framework/config.php`, runtime берёт config snapshot оттуда и не пересобирает его на лету;
- top-level `config/*.php` мерджатся в детерминированном отсортированном порядке;
- associative arrays мерджатся рекурсивно;
- list arrays заменяются целиком;
- optional overlay `config/environments/<app.env>.php` применяется после базовой сборки;
- `require` изолирован, поэтому config file не получает доступ к локальному scope loader'а;
- config cache остаётся explicit snapshot: он может устареть относительно source files и `.env`, пока не будет явно перестроен.
- runtime cache snapshot не принимается “на доверии”: loader валидирует cache type/version metadata до использования snapshot.

### Bootstrap flow matrix

| View | Config source | Route source | Notes |
| --- | --- | --- | --- |
| HTTP runtime | `ProjectConfigLoader::loadRuntime()` | `RoutesFileLoader::load()` | использует explicit cache snapshots, если они существуют |
| CLI runtime | `ProjectConfigLoader::loadRuntime()` | n/a | framework commands и app commands делят тот же runtime config view |
| Source inspection | `ProjectConfigLoader::loadSource()` | `RoutesFileLoader::loadSource()` | всегда bypass current cache snapshots |
| Recovery CLI path | `ProjectConfigLoader::loadRecovery()` | n/a | используется для `cache:clear`, чтобы recovery не зависел от совместимости config cache |

### Шаг 3. Fixed provider order

После загрузки config обе factory создают `Bootstrapper`, но с разным provider graph.

#### HTTP provider order

1. `SharedServicesProvider`
2. `ConfiguredServicesProvider`
3. `HttpCoreServicesProvider`
4. `RoutingServiceProvider`
5. `HttpKernelProvider`

#### CLI provider order

1. `SharedServicesProvider`
2. `ConfiguredServicesProvider`
3. `ConsoleCommandsProvider`
4. `ConsoleKernelProvider`

Инвариант:

- provider order hardcoded;
- providers internal-only;
- приложение не конфигурирует provider list;
- `config/`, `routes/web.php` и `commands/console.php` остаются primary app model;
- команды с prefix `config:`, `route:`, `cache:` и `container:` зарезервированы за framework core.

### Provider ownership matrix

- `SharedServicesProvider` владеет только truly shared bootstrap state:
  - `Config`
- `ConfiguredServicesProvider` владеет только user-defined container slice:
  - validated container config
  - application of bindings/singletons/aliases
- `HttpCoreServicesProvider` владеет только HTTP-specific PSR-7/HTTP services
- `RoutingServiceProvider` владеет только route boot state и `Router`
- `ConsoleCommandsProvider` владеет только command boot state и framework-owned internal CLI commands
- `ConsoleKernelProvider` владеет только CLI execution graph

Это правило закрепляет checkpoint outcome: shared service не должен “жить понемногу везде”.

### Шаг 4. Register phase

На фазе `register` providers только описывают service graph.

Ничего нельзя резолвить из ещё не собранного контейнера.

### Шаг 5. Build phase

`ContainerBuilder->build()` материализует container.

Инвариант:

- container explicit-only;
- duplicate definition ids и alias ids запрещены fail-fast;
- app container slice не может silently shadow framework-owned ids или aliases;
- alias cycles и service cycles считаются ошибкой;
- service factory допускает либо zero-argument форму, либо один `ContainerInterface`-compatible параметр;
- invocation mode factory (`requiresContainer`) вычисляется один раз при регистрации, а не в runtime hot path;
- observable container snapshot собирается на register phase и не зависит от runtime service resolution.

### Шаг 6. Boot phase

Только bootable providers получают `BootstrapContext`.

Они заполняют dedicated boot state:

- HTTP:
  - `RouteRegistry`
  - `GlobalMiddlewareRegistry`
- CLI:
  - `CommandRegistry`

Инвариант:

- registry single-assignment;
- чтение registry до инициализации считается lifecycle error;
- bootstrap intentionally fail-fast, если app seams (`routes/web.php`, `commands/console.php`) невалидны;
- routes/commands files должны оставаться relative project paths и не могут escape-нуться за пределы `basePath`;
- если существует route cache, HTTP bootstrap использует уже exportable `RouteIndex` и не требует `routes/web.php` в runtime path.

---

## 6. HTTP Runtime Lifecycle

### Шаг 1. Front controller

`public/index.php`:

- получает `HttpRuntime` из `bootstrap/app.php`;
- создаёт request через `RequestFactory`;
- передаёт request в `Application`;
- передаёт response в `ResponseEmitter`;
- задаёт transport policy для `HEAD` через `emitBody: false`.

### Шаг 2. Global middleware pipeline

`Application` собирает top-level global pipeline один раз в constructor, а `Application::handle()` только запускает его на конкретном request.

Инвариант:

- первым global middleware всегда выступает `ErrorHandlingMiddleware`;
- пользовательские global middleware идут после него;
- fallback handler глобального pipeline — `RouteDispatcher`.

### Шаг 3. Route matching

`RouteDispatcher` вызывает `Router::match(method, path)`.

Инвариант:

- path нормализуется один раз на boundary `Router`;
- static routes приоритетнее dynamic routes;
- route template компилируется один раз внутри `Route` и переиспользуется и для matching, и для URL generation;
- segment parameters могут иметь локальные constraints вида `{id:\d+}`;
- constraint mismatch считается `404`, а не `405`;
- `HEAD` умеет fallback к `GET`, если отдельный `HEAD` маршрут не определён;
- `405 Allow` включает `HEAD`, если path поддерживает `GET`.

### Шаг 4. Route attachment

При успешном match route и route params кладутся в request attributes:

- `framework.route`
- `framework.route_params`

Это позволяет middleware и handlers читать результат routing без прямой зависимости от `Router`.

### Шаг 5. Route middleware pipeline

После match `RouteDispatcher` строит второй pipeline уже для route-level middleware.

Fallback handler этого pipeline — `RouteHandler`.

### Шаг 6. Handler resolution

`HandlerResolver` принимает:

- `Closure`
- любой callable
- `class-string`

Если definition — `class-string`, container обязан вернуть объект, пригодный к исполнению.

### Шаг 7. Error boundary

`ErrorHandlingMiddleware` различает два типа ошибок:

- `HttpException` — controlled client-facing HTTP semantics;
- любой другой `Throwable` — unexpected execution failure.

`app.debug` управляет степенью детализации `500`.

### Шаг 8. Response emission

`ResponseEmitter`:

- очищает active cleanable output buffer перед emission status/headers;
- отправляет explicit HTTP status line из protocol version, status code и reason phrase response;
- отправляет headers;
- при `emitBody=false` и known body size синтезирует `Content-Length`, если он ещё не задан;
- при необходимости пропускает body emission;
- читает body stream chunked.

На этом HTTP request lifecycle заканчивается.

---

## 7. CLI Runtime Lifecycle

### Шаг 1. CLI entrypoint

`bin/console`:

- получает `ConsoleRuntime` из `bootstrap/console.php`;
- берёт `$_SERVER['argv']`;
- строит `CommandInput` через `ArgvInputFactory`;
- создаёт `ConsoleOutput`;
- вызывает `ConsoleApplication::run()`;
- завершает процесс через `exit($code)`.

### Шаг 2. Command registration bootstrap

Во время boot `ConsoleCommandsProvider`:

- читает `console.commands` из config;
- загружает `commands/console.php` через scope-isolated `require` с project-bound path validation;
- валидирует, что registrar callable;
- добавляет встроенные framework commands:
  - `config:cache`
  - `config:show`
  - `route:cache`
  - `route:list`
  - `cache:clear`
  - `container:debug`
- отклоняет прикладные команды с reserved prefixes `config:`, `route:`, `cache:`, `container:`;
- инициализирует `CommandRegistry`.

Operational detail:

- `bin/console cache:clear` bootstraps console runtime через recovery path на source config, чтобы несовместимый `config.php` cache snapshot не блокировал очистку кэша.
- `container:debug` строит inspection snapshot через register phase того же console provider graph, но не резолвит service factories и не материализует runtime services.

### Шаг 3. Parser contract

`ArgvInputFactory` фиксирует narrow parser semantics:

- `argv[0]` считается script name и игнорируется;
- первый remaining token — command name;
- `--key=value` -> option со строковым значением;
- `--flag` -> option со значением `true`;
- `--` завершает option parsing;
- repeated options -> last wins;
- всё остальное -> positional args.

Явно вне `v0`:

- short options;
- bundled flags;
- `--key value`;
- interactive prompts;
- ANSI styling.

### Шаг 4. Missing/unknown command boundary

`ConsoleApplication` сначала проверяет наличие command name и command registration.

Если команда отсутствует или неизвестна:

- пишет short usage в `stderr`;
- печатает список зарегистрированных команд;
- возвращает exit code `1`.

### Шаг 5. Command resolution

`CommandResolver` принимает только `class-string<CommandInterface>`.

Он резолвит команду через container и валидирует, что объект реализует `CommandInterface`.

### Шаг 6. Command execution

Команда получает:

- `CommandInput`
- `ConsoleOutput`

Framework не навязывает return-value magic:

- команда сама решает, что писать в stdout/stderr;
- команда сама возвращает exit code.

### Шаг 7. Console error boundary

`ConsoleApplication` ловит неожиданный `Throwable` и делегирует его в `ConsoleErrorRenderer`.

Поведение зависит от `app.debug`:

- `false` -> `Command failed.`
- `true` -> class, message, file:line и trace

### Шаг 8. Process exit

`bin/console` завершает процесс тем кодом, который вернул `ConsoleApplication`.

CLI path не делает дополнительной магии поверх exit codes.

---

## 8. Ключевые инварианты подсистем

### Config

- `Config` immutable;
- dotted access только читает данные;
- config files должны возвращать arrays;
- config loader детерминирован и scope-isolated.

### Container

- сервис либо зарегистрирован явно, либо его нет;
- autowiring по умолчанию отсутствует;
- singleton и transient semantics различаются явно;
- container может вернуть только то, что было описано definitions graph;
- observable container contract отделён от export contract и живёт как sidecar inspection snapshot;
- framework и application registrations имеют явный ownership/origin в inspection view.

### Routing

- routing boundary владеет нормализацией path;
- match возвращает typed result, а не `null`;
- route template один и тот же для matching и generation;
- URL generation fail-fast: missing, unexpected и constraint-violating parameters считаются `UrlGenerationException`;
- route names уникальны;
- route groups живут только на registration layer.

### Console

- commands регистрируются явно;
- command names trimmed, non-empty и уникальны;
- `CommandRegistry` не допускает partial initialization;
- parser contract узкий и детерминированный;
- `container:debug` наблюдает definitions/aliases без service resolution side effects.

### Foundation / Bootstrap

- runtime factories тонкие;
- bootstrap lifecycle fixed-order;
- providers internal-only;
- dedicated registries защищают boot state от неявной частичной сборки.

---

## 9. Точки расширения

В текущем core расширяемость специально узкая.

### Shared

- `config/*.php`
- environment overlays
- container `bindings`, `singletons`, `aliases`
- explicit cache files в `var/cache/framework/`
- read-only container inspection через `container:debug`

Cache-specific contract:

- `config:cache` принимает только exportable config values;
- cache-safe container definitions ограничены class-string и exportable static callable references;
- closure/object service definitions остаются допустимы только в uncached mode.

### HTTP

- `routes/web.php`
- global middleware
- route middleware
- handlers

Route cache contract:

- `route:cache` принимает только class-string handlers;
- route middleware в cached mode должны быть class-string'ами middleware classes;
- closure handlers и instance-based route middleware остаются допустимы только в uncached mode.

### CLI

- `commands/console.php`
- class commands в `app/Console/`
- framework internal commands:
  - `config:cache`
  - `config:show`
  - `route:cache`
  - `route:list`
  - `cache:clear`
  - `container:debug`

Это сделано специально: система должна оставаться реконструируемой.

---

## 10. Сознательные ограничения и trade-offs

### Почему container explicit-only

Потому что для этого core важнее:

- analyzability;
- error clarity;
- явная цена зависимости;
- учебная реконструируемость.

### Почему providers internal-only

Потому что bootstrap lifecycle нужен framework core, но пока не должен становиться отдельным public API приложения.

### Почему CLI parser намеренно узкий

Потому что первая версия решает задачу app command execution, а не построение feature-rich console platform.

### Почему нет built-in `help` / `list` как отдельных commands

Потому что для `v0` достаточно boundary-level usage message и registered names. Это дешевле и честнее, чем ранний мини-Symfony Console.

---

## 11. Типичные failure modes

### Неправильный config

Если config files отсутствуют, не читаются или возвращают не массивы, bootstrap ломается через `InvalidConfigurationException`.

Если `var/cache/framework/config.php` существует, но содержит несовместимый cache type/version metadata, normal runtime bootstrap тоже ломается через `InvalidConfigurationException`; recovery path в этом случае — `bin/console cache:clear`.

Если application container slice пытается переиспользовать framework-owned service id или alias id, bootstrap ломается fail-fast на registration phase, а не поздно в runtime resolution.

### Неправильный routes file

Если `routes/web.php` не существует, escape-нул за пределы project base path или не возвращает callable registrar, HTTP runtime не соберётся.

### Неправильный commands file

Если `commands/console.php` не существует, escape-нул за пределы project base path или не возвращает callable registrar, CLI runtime не соберётся.

### Нарушение bootstrap lifecycle

Чтение `RouteRegistry`, `GlobalMiddlewareRegistry` или `CommandRegistry` до `initialize()` считается lifecycle error.

### Не зарегистрированный handler / middleware / command

Если `class-string` не резолвится контейнером, ошибка поднимается в boundary соответствующего runtime:

- HTTP -> `500`
- CLI -> debug/non-debug console error и exit code `1`

### Неверный command class

Если registered command class не реализует `CommandInterface`, bootstrap ломается fail-fast ещё на registration layer.

---

## 12. Как читать код, чтобы понять систему

### Первый проход: shared bootstrap

1. `bootstrap/app.php`
2. `bootstrap/console.php`
3. `src/Foundation/ApplicationFactory.php`
4. `src/Foundation/ConsoleApplicationFactory.php`
5. `src/Foundation/Bootstrap/Bootstrapper.php`
6. `src/Foundation/Bootstrap/Provider/*`

Цель:
- понять shared causality и runtime split.

### Второй проход: HTTP axis

1. `public/index.php`
2. `src/Foundation/Application.php`
3. `src/Foundation/HttpRuntime.php`
4. `src/Http/MiddlewareDispatcher.php`
5. `src/Http/RouteDispatcher.php`
6. `src/Http/HandlerResolver.php`
7. `src/Routing/Router.php`

Цель:
- восстановить HTTP request lifecycle.

### Третий проход: CLI axis

1. `bin/console`
2. `src/Foundation/ConsoleRuntime.php`
3. `src/Console/ArgvInputFactory.php`
4. `src/Console/ConsoleApplication.php`
5. `src/Console/CommandResolver.php`
6. `commands/console.php`

Цель:
- восстановить CLI process flow.

### Четвёртый проход: config и container

1. `src/Config/EnvironmentLoader.php`
2. `src/Config/ConfigLoader.php`
3. `src/Config/Config.php`
4. `src/Container/ContainerBuilder.php`
5. `src/Container/Container.php`
6. `src/Foundation/Bootstrap/ConfiguredServicesRegistrar.php`

### Пятый проход: calibration через tests

1. `tests/Foundation/ApplicationFactoryTest.php`
2. `tests/Foundation/ConsoleApplicationFactoryTest.php`
3. `tests/Foundation/Bootstrap/ProviderIntegrationTest.php`
4. `tests/Foundation/Bootstrap/ConsoleProviderIntegrationTest.php`
5. `tests/Console/*`

---

## 13. Процесс разработки

Каждое существенное изменение проходит один и тот же цикл:

1. определить границы и инварианты;
2. внести изменение;
3. обновить документацию;
4. прогнать `composer qa`;
5. прогнать `composer test`;
6. зафиксировать шаг в `artifacts/execution/`.

Текущий baseline после cache/observability phase:

- `composer qa` — green
- `composer test` — green
- `144 tests / 534 assertions`

---

## 14. Краткая модель системы в одной фразе

Framework core — это explicit shared bootstrap layer с двумя runtime axes: HTTP kernel для request/response execution и console kernel для command execution, которые делят config и container, но не смешивают runtime-specific semantics.
