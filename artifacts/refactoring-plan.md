# Refactoring Plan — Framework

Дата актуализации: `2026-03-21`

---

## 1. Назначение документа

Это не wishlist и не список всего, чего ещё нет.

Это живая карта инженерного состояния проекта:

- что уже стабилизировано;
- какие реальные pressure points ещё остались;
- куда имеет смысл двигаться дальше без platform drift.

---

## 2. Текущее состояние ядра

**Framework core стабилизирован как shared bootstrap + two runtime axes.**

Baseline:

- `composer qa` — green
- `composer test` — green
- `98 tests / 290 assertions`

Система сейчас выглядит так:

```text
HTTP
public/index.php
  -> bootstrap/app.php
  -> ApplicationFactory::createRuntime()
  -> Bootstrapper
  -> HttpRuntime

CLI
bin/console
  -> bootstrap/console.php
  -> ConsoleApplicationFactory::createRuntime()
  -> Bootstrapper
  -> ConsoleRuntime

Shared bootstrap
EnvironmentLoader
  -> ConfigLoader
  -> Bootstrapper
     -> [register all providers]
     -> [build container]
     -> [boot bootable providers]
```

### Что уже работает

- PSR-7 / PSR-15 / PSR-11 boundaries
- stateless environment bootstrap
- multi-file config + environment overlays
- explicit container без runtime reflection в factory hot path
- HTTP routing:
  - static and dynamic routes
  - named routes
  - URL generation
  - route groups
  - `HEAD -> GET` fallback
  - correct `Allow` header semantics для `405`
- HTTP error boundary с controlled `HttpException` hierarchy
- CLI command runtime:
  - explicit command registration
  - deterministic argv parsing
  - container-based class commands
  - debug / non-debug console error boundary

---

## 3. Закрытые задачи

### 3.1 ApplicationFactory: God Object -> thin orchestrator

Ручная сборка runtime graph убрана из `ApplicationFactory`.

### 3.2 Internal bootstrap providers

Введён fixed internal lifecycle:

- `ServiceProviderInterface`
- `BootableProviderInterface`
- `Bootstrapper`

### 3.3 Reflection убрана из container hot path

Factory invocation mode предвычисляется при регистрации.

### 3.4 HEAD correctness

- router поддерживает `HEAD -> GET`
- `405 Allow` включает `HEAD`, если path поддерживает `GET`
- response emitter умеет suppress body на transport boundary
- полный flow покрыт integration tests

### 3.5 EnvironmentLoader stateless

Убран скрытый process-level path cache.

### 3.6 Scope-isolated `require`

`ConfigLoader`, `RoutesFileLoader` и `CommandsFileLoader` изолируют scope app files.

### 3.7 Multi-file config

`config/*.php` детерминированно мерджатся, затем может накладываться environment overlay.

### 3.8 Routing capability layer

Реализованы:

- named routes
- URL generation
- route groups

### 3.9 Controlled HTTP failures

`HttpException` hierarchy даёт честный путь к `4xx/405` без обхода error boundary.

### 3.10 Console kernel v0

Реализован второй runtime axis:

- `ConsoleApplicationFactory`
- `ConsoleRuntime`
- `CommandCollector` / `CommandRegistry`
- `ArgvInputFactory`
- `ConsoleApplication`
- `ConsoleErrorRenderer`
- `bin/console`
- `commands/console.php`

Ключевой результат: framework больше не HTTP-only, но при этом не превратился в plugin platform.

---

## 4. Активные точки давления

### P3. `ReflectionClass::newInstance()` в `ContainerBuilder`

**Где:** [`src/Container/ContainerBuilder.php`](/C:/OSPanel/home/framework.ru/src/Container/ContainerBuilder.php)

После проверки конструктора локально кажется, что `new $class()` был бы проще.
Но текущее toolchain-ограничение остаётся прежним:

- suppression-free вариант пока не найден;
- Psalm начинает терять analyzability;
- для этого проекта это хуже, чем маленький reflection tail.

**Статус:** accepted low-priority debt.

**Серьёзность:** низкая.

---

### P6. `BootstrapContext` возвращает concrete `Container`

**Где:** [`src/Foundation/Bootstrap/BootstrapContext.php`](/C:/OSPanel/home/framework.ru/src/Foundation/Bootstrap/BootstrapContext.php)

`container(): Container` жёстче, чем `ContainerInterface`.
Пока это не operational problem, но это minor extensibility mismatch.

**Статус:** low-priority design debt.

**Серьёзность:** низкая.

---

### P7. Checkpoint после split runtime axes

После введения CLI path система стала сильнее, но появился новый риск:

- shared bootstrap может начать размываться;
- provider graph для HTTP и CLI может начать дублироваться неструктурно;
- config model может тихо расползтись в два почти независимых мира.

Это не дефект текущего кода. Это системный checkpoint, который нужно пройти до следующей крупной capability.

Что надо проверить:

- не дублируются ли shared registrations между `HttpCoreServicesProvider` и `ConsoleKernelProvider`;
- не начал ли bootstrap слой превращаться в скрытый второй framework внутри framework;
- остаётся ли app model простой:
  - `config/`
  - `routes/web.php`
  - `commands/console.php`

**Статус:** следующий обязательный архитектурный шаг.

**Серьёзность:** средняя.

---

## 5. Что сознательно не входит в текущий plan

Ниже перечислены отсутствующие компоненты, которые не считаются дефектами ядра.

- public provider layer
- autowiring by default
- PSR-14 events
- attribute routing
- named middleware aliases
- auth/session/CSRF
- ORM / queue / scheduler
- advanced console UX:
  - short options
  - interactive prompts
  - ANSI styling
  - signature DSL
  - autodiscovery

---

## 6. Следующий план развития

### Фаза G. Закрыта: Console kernel v0

Реализован минимальный CLI runtime без platform explosion.

Что важно:

- CLI делит bootstrap, config и container с HTTP;
- CLI не делит HTTP-specific runtime graph;
- команды регистрируются явно;
- framework не навязывает magic command model.

---

### Следующий обязательный шаг: архитектурный checkpoint

До следующей capability нужно ответить на три вопроса:

1. Shared bootstrap действительно остался shared, или начался drift между HTTP и CLI?
2. Не появился ли лишний слой сложности в provider lifecycle?
3. Остаётся ли app model простой и реконструируемой?

Это не теоретический ритуал. После split runtime axes без такого checkpoint очень легко пойти в feature accumulation и потерять управляемость core.

---

### После checkpoint: наиболее рациональный следующий горизонт

Если checkpoint зелёный, следующий сильный кандидат — не Events и не Auth stack, а read-only introspection commands поверх уже существующего CLI axis:

- `route:list`
- `config:show`
- возможно `container:debug`

Почему это сильнее, чем новый subsystem:

- reutilizes уже собранный console kernel;
- даёт практическую ценность без новых внешних зависимостей;
- усиливает observability и учебную реконструируемость системы;
- не размывает архитектуру новой execution axis.

---

## 7. Приоритеты

```text
Немедленно:   пройти архитектурный checkpoint после split runtime axes
Опционально:  revisit P3 только при suppression-free решении
Опционально:  revisit P6 только если появится реальная потребность в container abstraction на boot phase
Потом:        выбрать следующую capability только после checkpoint
Горизонт:     observability-oriented console commands, а не platform-heavy subsystems
```

---

## 8. Принципы, которые нельзя нарушать

1. Каждое существенное изменение проходит полный QA.
2. Новая capability добавляется только после стабилизации предыдущей.
3. Measured debt важнее optimization by suspicion.
4. Internal bootstrap providers не становятся public extension API без отдельного решения.
5. Документация, код и execution log должны оставаться синхронизированы.
