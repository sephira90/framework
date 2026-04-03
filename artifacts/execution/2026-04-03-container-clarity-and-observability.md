# 2026-04-03 Container Clarity And Observability

## Context
- После cache/runtime refactor и observability phase следующий согласованный шаг был не новый subsystem growth, а container clarity.
- Цели шага:
  - сделать container registrations наблюдаемыми без service resolution;
  - запретить silent overrides framework-owned ids;
  - встроить read-only `container:debug [id] [--source]` в console axis;
  - не смешивать inspection contract с cache export contract.

## Key Findings
- В предыдущем состоянии application container config мог silently shadow framework-owned service ids, а ошибка проявлялась поздно в runtime resolution.
- Honest `container:debug` нельзя строить поверх уже собранного `Container`, потому что runtime graph теряет ownership/origin и raw definition shape.
- Наблюдаемая модель контейнера должна сниматься на register phase как sidecar snapshot, а не восстанавливаться по косвенным признакам.

## Decisions
- В `ContainerBuilder` добавлен sidecar inspection model:
  - `ContainerInspectionSnapshot`
  - `ContainerDefinitionDescriptor`
  - `ContainerAliasDescriptor`
  - enums для `owner`, `lifecycle`, `definition_kind`
- Все framework providers теперь регистрируют container entries с явным `owner=framework` и `origin=self::class`.
- `ConfiguredServicesRegistrar` регистрирует application entries с origins:
  - `container.bindings`
  - `container.singletons`
  - `container.aliases`
- Duplicate definition ids и alias ids теперь fail-fast.
- Конфликты между app slice и framework-owned ids больше не доходят до runtime resolution.
- Добавлен internal command:
  - `container:debug [id] [--source]`
- `container:debug`:
  - по умолчанию читает runtime config view;
  - `--source` обходит config cache;
  - не резолвит services, handlers, middleware или commands;
  - печатает deterministic JSON.
- `container:` добавлен в reserved framework command prefixes.

## What This Changes
- Container model стала operationally observable и лучше реконструируема.
- Ошибки ownership/collision теперь проявляются на registration boundary, а не как поздние secondary failures.
- Framework получил симметричный observability слой:
  - config view
  - route view
  - container view

## Changed Files
- `src/Container/*` inspection types и enums
- `src/Container/ContainerBuilder.php`
- `src/Foundation/Bootstrap/ConfiguredServicesRegistrar.php`
- `src/Foundation/Bootstrap/Provider/*`
- `src/Foundation/ConsoleApplicationFactory.php`
- `src/Console/Internal/ContainerDebugCommand.php`
- `tests/Container/*`
- `tests/Foundation/*`
- `README.md`
- `docs/framework-architecture.md`
- `artifacts/refactoring-plan.md`

## Validation
- `C:\OSPanel\modules\PHP-8.4\php.exe vendor/bin/phpunit --configuration phpunit.xml.dist`: passed
- `phpunit`: `144` tests, `534` assertions
- `C:\OSPanel\modules\PHP-8.4\php.exe qa.php qa`: passed
- Targeted RED/GREEN verification:
  - `tests/Container/ContainerBuilderTest.php`
  - `tests/Container/ContainerInspectionTest.php`
  - `tests/Foundation/Bootstrap/ConsoleProviderIntegrationTest.php`
  - `tests/Foundation/ConsoleApplicationFactoryTest.php`
  - `tests/Foundation/CacheCommandsIntegrationTest.php`

## Open Risks
- `container:debug` сейчас показывает console runtime container view, а не отдельную HTTP/CLI axis matrix; это осознанное ограничение текущего шага.
- Cache format versioning по-прежнему глобально сцеплен между `config` и `routes`; этот шаг намеренно не менял compatibility policy.
- Sidecar inspection snapshot живёт только в register phase и не сериализуется в cache files; это правильно для честной observability, но future export/manifest seams нужно будет проектировать отдельно.

## Next Actions
- Использовать `container:debug` как основной calibration tool для container/boot changes.
- Брать следующий шаг только по новому measured signal.
- Если compatibility pressure вырастет, следующим rational step будет controlled cache format evolution, а не platform-heavy growth.
