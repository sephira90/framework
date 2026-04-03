# Refactoring Plan — Framework

Дата актуализации: `2026-04-03`

---

## 1. Назначение документа

Это не wishlist и не каталог “всего, чего ещё нет”.

Это живая карта инженерного состояния ядра:

- что уже стабилизировано;
- где реально находятся текущие pressure points;
- какие шаги дальше усиливают систему, а не размывают её.

---

## 2. Текущее состояние ядра

**Framework core сейчас — это shared bootstrap layer, two runtime axes и explicit cache/runtime model.**

Система состоит из:

- HTTP runtime
- Console runtime
- shared bootstrap lifecycle
- explicit config cache и route cache
- read-only observability commands для source/runtime views

Текущее ядро уже включает:

- stateless environment bootstrap;
- multi-file config + environment overlays;
- explicit `config:cache`, `route:cache`, `cache:clear`;
- versioned cache metadata validation;
- precompiled `RouteIndex` для routing hot path;
- split validation/apply для configured container services;
- minimal console kernel с explicit command registration;
- observability commands:
  - `config:show`
  - `route:list`
  - `container:debug`

---

## 3. Что уже стабилизировано

### 3.1 Runtime assembly

- `ApplicationFactory` и `ConsoleApplicationFactory` остаются thin orchestrators.
- bootstrap lifecycle остаётся fixed-order и internal-only.
- shared bootstrap не превратился в public provider platform.

### 3.2 Config/runtime model

- runtime читает config либо из source, либо из explicit snapshot;
- cache snapshot не rebuild-ится автоматически;
- stale cache считается допустимым operational state до явного rebuild;
- incompatible config cache валидируется fail-fast по type/version metadata.

### 3.3 Routing hot path

- `RouteIndex` убрал плоский scan всех dynamic routes;
- dynamic matching теперь bucketed по `segmentCount` и `firstLiteralSegment`;
- route cache сохраняет compiled route index, а не сырой registration list;
- routing contract сохранён:
  - static priority
  - `HEAD -> GET`
  - `405 Allow`
  - named routes
  - URL generation

### 3.4 Console axis

- commands регистрируются явно через `commands/console.php`;
- framework-owned commands живут в том же runtime:
  - `config:cache`
  - `config:show`
  - `route:cache`
  - `route:list`
  - `cache:clear`
  - `container:debug`
- reserved prefixes `config:`, `route:`, `cache:`, `container:` защищены.

### 3.5 Container clarity

- container registrations теперь сохраняют sidecar inspection snapshot на register phase;
- definitions и aliases имеют явные `owner` и `origin`;
- duplicate ids запрещены fail-fast;
- application config не может silently shadow framework-owned ids;
- `container:debug` показывает runtime/source container view без service resolution.

### 3.6 Recovery semantics

- `cache:clear` остаётся operational recovery path;
- для этого `bin/console cache:clear` поднимается через source config recovery bootstrap и не зависит от совместимости `config.php` cache snapshot.

---

## 4. Checkpoint outcome

Последний архитектурный checkpoint дал такой вывод:

- shared bootstrap остался действительно shared;
- provider ownership можно формулировать жёстко и без двусмысленности;
- runtime/cache model стала сложнее, но пока остаётся реконструируемой;
- главный риск больше не routing performance, а hidden operational complexity вокруг source/runtime divergence и cache compatibility.

Принятое правило ownership:

- `SharedServicesProvider` владеет только truly shared state;
- `ConfiguredServicesProvider` владеет только user container slice;
- `HttpCoreServicesProvider` владеет только HTTP core;
- `RoutingServiceProvider` владеет только route boot state и router;
- `ConsoleCommandsProvider` владеет только command boot state и internal CLI commands;
- `ConsoleKernelProvider` владеет только CLI execution graph.

---

## 5. Активные точки давления

### P3. `ReflectionClass::newInstance()` в `ContainerBuilder`

Статус не изменился: это low-priority measured debt.

Трогать только при suppression-free решении, которое не ухудшает analyzability.

### P6. `BootstrapContext` и concrete `Container`

Статус не изменился: low-priority design mismatch.

Трогать только при реальной потребности в более мягкой bootstrap abstraction.

### P8. Новый bottleneck ещё не измерен

После route/cache refactor и observability phase следующий performance шаг нельзя выбирать “по ощущению”.

Нужно сначала получить новый measured signal:

- либо из perf harness;
- либо из реальной operational деградации;
- либо из profiling под более тяжёлым приложением.

### P9. Cache format evolution теперь имеет цену совместимости

После ввода versioned metadata любое изменение cache shape — это уже не локальный refactor, а format change.

Это нужно держать под контролем и не делать без причины.

---

## 6. Что сознательно не входит в ближайший горизонт

По-прежнему не считаются следующим рациональным шагом:

- public provider API
- autowiring by default
- events
- attribute routing
- auth/session/csrf
- ORM / queue / scheduler
- advanced console UX
- full compiled container

Причина та же: они расширяют platform surface быстрее, чем усиливают текущую модель.

---

## 7. Следующий приоритетный горизонт

### Немедленно

Не добавлять новую тяжёлую capability.

Сначала использовать и калибровать уже введённый observability layer:

- `config:show`
- `route:list`
- perf harness
- execution logs

### Ближайший сильный кандидат

После container clarity следующий сильный кандидат снова не heavy feature, а новый measured signal:

- сначала использовать `container:debug`, `config:show`, `route:list` и perf harness как calibration layer;
- только потом выбирать следующий structural step.

### Альтернатива, если сигнал придёт из compatibility seams

Если новый pressure point окажется в runtime/caching compatibility, следующим шагом должен быть не feature growth, а controlled cache format evolution:

- version bump policy;
- invalidation semantics;
- rollback clarity.

---

## 8. Приоритеты

```text
Сейчас:       стабилизировать и использовать observability layer
Потом:        брать только новый measured signal, а не гадание о bottleneck
Опционально:  controlled cache format evolution, если подтвердится compatibility pressure
Не делать:    autowiring, events, public provider API, full compiled container
```

---

## 9. Принципы, которые нельзя нарушать

1. Каждый существенный шаг проходит полный QA.
2. Cache/runtime complexity растёт только вместе с observability.
3. Recovery path должен быть operationally real, а не декларативным.
4. Export contract и inspection contract не смешиваются.
5. Documentation, code и execution log должны оставаться синхронизированы.
