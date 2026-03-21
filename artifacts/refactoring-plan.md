# Refactoring Plan — Framework

Дата актуализации: `2026-03-21`

---

## 1. Назначение документа

Живая карта инженерного состояния проекта:

- что реализовано и стабилизировано;
- какие ещё есть точки давления;
- куда двигаться дальше.

Не список "всего чего нет". Не wishlist. Карта реального давления.

---

## 2. Текущее состояние ядра

**v0 HTTP kernel стабилизирован.** Baseline: `78 tests / 217 assertions`. Все QA-инструменты зелёные.

```
public/index.php
  └─ bootstrap/app.php
       └─ ApplicationFactory::createRuntime()
            ├─ EnvironmentLoader (stateless)
            ├─ ConfigLoader (scope-isolated require)
            └─ Bootstrapper → [register all] → [build container] → [boot bootable]
                 ├─ CoreServicesProvider         (register only)
                 ├─ ConfiguredServicesProvider   (register only)
                 ├─ RoutingServiceProvider       (register + boot)
                 └─ HttpKernelProvider           (register + boot)
```

**Что работает:**

- PSR-7/PSR-15/PSR-11 compliance
- Static O(1) + dynamic regex routing
- HEAD → GET fallback в router
- Transport-level body suppression для HEAD (`emitBody: false`)
- Two-phase bootstrap: `register → build → boot`
- `ServiceProviderInterface` + `BootableProviderInterface` — раздельные контракты
- `RequiresContainer` flag вычислен один раз при регистрации, нет reflection в runtime
- `EnvironmentLoader` без static state
- Scope isolation через IIFE в `ConfigLoader` и `RoutesFileLoader`
- Multi-file config с deterministic merge и environment overlays
- `ErrorHandlingMiddleware` как outer boundary для всего HTTP path
- Container: bindings, singletons, aliases, circular dependency detection, alias cycle detection

---

## 3. Закрытые задачи

### 3.1 Рефакторинг ApplicationFactory (God Object → Thin Orchestrator)

Было: ~310 строк, 11 private static методов, всё в одном месте.
Стало: ~50 строк, два метода, делегирует в `Bootstrapper`.

### 3.2 Декомпозиция bootstrap в providers

`CoreServicesProvider`, `ConfiguredServicesProvider`, `RoutingServiceProvider`, `HttpKernelProvider`.

### 3.3 Раздельные интерфейсы lifecycle

`ServiceProviderInterface` (только `register`) + `BootableProviderInterface` (только `boot`).
Устранены пустые `boot()` с `unset($context)`.

### 3.4 Reflection из hot-path контейнера

`ServiceDefinition::requiresContainer` вычисляется в `ContainerBuilder::factoryRequiresContainer()` один раз.
`Container::invokeFactory()` больше не создаёт `ReflectionFunction` в runtime.

### 3.5 HEAD semantics

- Router: explicit HEAD route имеет приоритет; fallback HEAD → GET для static и dynamic routes.
- `405 Method Not Allowed`: `Allow` включает `HEAD`, если путь поддерживает `GET`.
- `ResponseEmitter::emit(response, emitBody: bool)` — transport-level policy.
- `public/index.php`: `emitBody: strcasecmp($request->getMethod(), 'HEAD') !== 0`.
- Покрыто тестами на уровне Router, RouteDispatcher, ResponseEmitter и ApplicationFactory.

### 3.6 EnvironmentLoader stateless

Убран `static $loadedPaths`. Повторная загрузка из того же basePath работает корректно.

### 3.7 Scope isolation для require

`ConfigLoader::requireFile()` и `RoutesFileLoader::requireFile()` используют IIFE, чтобы файл не получал доступ к локальным переменным loader'а.

### 3.8 $config через контейнер, не через closure capture

`CoreServicesProvider` достаёт `Config` через `ContainerAccessor::get()`, а не через `use ($config)`.

### 3.9 Test hardening

Покрыты изолированными тестами:
- `Route`, `Router`, `RouteCollector`
- `Container`, `ContainerBuilder`
- `HandlerResolver`, `MiddlewareResolver`, `MiddlewareDispatcher`
- `RouteDispatcher`, `ErrorResponseFactory`, `ResponseEmitter`
- `Bootstrapper`, `GlobalMiddlewareRegistry`, `RouteRegistry`
- `ProviderIntegration` (lifecycle scenarios)
- `Env`, `Config`, `ConfigLoader`, `EnvironmentLoader`
- `ApplicationFactory` (end-to-end)

### 3.10 Path normalization contract

- `Router::match()` нормализует входной path один раз на boundary routing layer.
- `Route` больше не выполняет повторную defensive normalization в matching helpers.
- Matching seam сделан явным через методы:
  - `matchesNormalizedPath()`
  - `extractParametersFromNormalizedPath()`
- Это переводит нормализацию из "размытой коллективной ответственности" в
  "одну явную обязанность Router".

### 3.11 ServiceDefinition accessors

- `ServiceDefinition` больше не экспонирует runtime data через public properties.
- Container читает definition через явные accessor-методы:
  - `factory()`
  - `isShared()`
  - `requiresContainer()`
- Это не меняет semantics resolution, но выравнивает container internals с общим стилем ядра:
  immutable state остаётся закрытым, а доступ к нему проходит через именованный контракт.

### 3.12 Named routes and URL generation

- `RouteCollector` теперь возвращает optional fluent `RouteBuilder`, через который route можно именовать:
  - `$routes->get('/users/{id}', Handler::class)->name('users.show');`
- `Route` хранит optional route name и умеет генерировать path из route parameters.
- `Router` индексирует named routes и предоставляет `url(name, parameters)`.
- Duplicate route names считаются bootstrap-time invariant violation и ломают сборку router'а fail-fast.

### 3.13 Route groups

- `RouteCollector::group()` добавляет nested prefix и inherited middleware без новой runtime subsystem.
- Group state живёт только на registration layer и восстанавливается через `try/finally`, поэтому группы не протекают в соседние registrations.
- Nested groups композиционно собирают:
  - path prefix;
  - middleware order от outer group к inner group и затем к route-level middleware.
- Capability покрыта на двух уровнях:
  - `RouteCollectorTest` — prefix/middleware inheritance и state restoration;
  - `ApplicationFactoryTest` — реальный HTTP flow с group middleware.

### 3.14 HttpException hierarchy

- В `Framework\Http\Exception` добавлена controlled HTTP exception hierarchy:
  - `NotFoundException`
  - `ForbiddenException`
  - `UnprocessableEntityException`
  - `MethodNotAllowedException`
- `ErrorHandlingMiddleware` теперь различает:
  - explicit `HttpException` -> `ErrorResponseFactory::fromHttpException()`
  - неожиданный `Throwable` -> `internalServerError()`
- Это открывает controlled `4xx/405` semantics из бизнес-логики без обхода router/error boundary.

### 3.15 Multi-file config

- `ConfigLoader` теперь принимает не только один файл, но и `config/` directory.
- Базовая конфигурация собирается из top-level `config/*.php` в детерминированно отсортированном порядке.
- Merge strategy явно зафиксирована:
  - associative arrays мерджатся рекурсивно;
  - list arrays не склеиваются, а заменяются override-значением целиком.
- После базовой сборки может применяться environment-specific overlay из `config/environments/<app.env>.php`.
- Текущий skeleton уже dogfooding'ит эту модель через:
  - `config/app.php`
  - `config/http.php`
  - `config/container.php`
- Capability покрыта isolated config tests и не меняет public bootstrap contract: `ApplicationFactory` по-прежнему собирает runtime только из `basePath`.

---

## 4. Активные точки давления

### P3. `ReflectionClass::newInstance()` в ContainerBuilder

**Где:** [ContainerBuilder.php](src/Container/ContainerBuilder.php) — метод `instantiateClass()`

```php
return $reflection->newInstance(); // можно заменить на new $class()
```

После проверки "конструктор без required аргументов" локально кажется, что
`new $class()` должен быть проще. Но в текущем toolchain это решение не проходит
без деградации analyzability:
- Psalm не принимает такой вызов без дополнительного suppression/workaround;
- для этого проекта suppression ради косметического упрощения хуже, чем текущий reflection tail.

**Текущий вывод:** оставить пункт открытым как accepted low-priority debt, пока не появится
suppression-free реализация, которая не ухудшает статическую проверяемость.

**Серьёзность:** низкая (simplicity).

---

### P6. `BootstrapContext` возвращает конкретный `Container`

**Где:** [BootstrapContext.php](src/Foundation/Bootstrap/BootstrapContext.php)

`container(): Container` вместо `ContainerInterface`. Все провайдеры привязаны
к конкретной реализации. При желании декорировать или подменить контейнер
в boot-фазе потребует изменения сигнатуры.

**Серьёзность:** низкая (extensibility).

---

## 5. Что сознательно не входит в текущий plan

Ниже перечислены отсутствующие компоненты, которые не считаются дефектами ядра.
Это следующий архитектурный горизонт.

- **public provider layer** — app-level `config/providers.php` стиль Laravel/Symfony
- **autowiring** — контейнер намеренно explicit-only; analyzability важнее удобства
- **console kernel** — отдельный `ConsoleRuntime`
- **PSR-14 events**
- **attribute routing** (`#[Route('/path')]`)
- **named middleware** / middleware aliases
- **auth/session/CSRF/ORM/queue/scheduler**

---

## 6. План дальнейшего развития

### Фаза A. Закрыта: HEAD correctness и transport coverage

- `Allow` для `405` теперь включает `HEAD`, если путь поддерживает `GET`.
- Добавлен integration-тест полного пути `HEAD /route → application → emitter`.

---

### Фаза B. Quality fixes (без изменения контрактов)

**B1. Пересмотреть P3 только при suppression-free решении**

Пункт не должен продвигаться ценой `@psalm-suppress` ради косметического выигрыша.
Возвращаться к нему имеет смысл только если появится чистая реализация без потери analyzability.

---

### Фаза C. Закрыта: Named Routes + URL Generation

- Добавлен fluent `RouteBuilder` для optional route naming.
- `Router::url()` генерирует path по имени маршрута и route parameters.
- Покрыты route naming, url generation, duplicate names и missing parameters.

---

### Фаза D. Закрыта: Route Groups

- `RouteCollector::group()` поддерживает nested prefix inheritance.
- Route groups наследуют middleware в порядке outer → inner → route.
- Пустая группа не оставляет residual state в collector.

---

### Фаза E. Закрыта: HttpException Hierarchy

- Бизнес-код может выбрасывать controlled HTTP-исключения, не обходя существующий error boundary.
- `ErrorResponseFactory` умеет строить response из `HttpException`.
- `ErrorHandlingMiddleware` различает client-facing HTTP failures и неожиданные ошибки исполнения.

---

### Фаза F. Закрыта: Multi-File Config

- `ConfigLoader` умеет собирать конфигурацию из `config/` directory.
- Top-level `.php` files мерджатся детерминированно.
- `config/environments/<app.env>.php` может накладываться поверх базовой сборки.
- Модель уже используется самим skeleton'ом проекта.

---

## 7. Приоритеты

```
Немедленно:   нет blocking cleanup после закрытия `ServiceDefinition` consistency fix
Следующая:    архитектурный checkpoint после роста capability surface и выбор следующего горизонта
Опционально:  revisit P3, только если найдётся suppression-free реализация
Потом:        выбрать следующую capability только после явной фиксации системной цели
Горизонт:     Console kernel, Events, Auth stack
```

---

## 8. Принципы, которые нельзя нарушать

1. **Каждое изменение проходит полный QA** (lint + cs + phpstan + psalm + tests).
2. **Новая capability — только после стабилизации предыдущей.**
3. **Measured debt, не optimization by suspicion** — reflection, allocations, bootstrap overhead
   трогаем только при воспроизводимом workload.
4. **Internal bootstrap providers не становятся public extension API** без отдельного решения.
5. **Три слоя в синхроне:** код + `docs/framework-architecture.md` + этот файл.
