# 2026-03-29 Observability Commands And Cache Hardening

## Context
- После cache/runtime refactor следующей задачей была не новая тяжёлая capability, а архитектурный checkpoint и observability layer поверх уже существующей runtime/cache модели.
- Цели шага:
  - синхронизировать roadmap и architecture docs с фактическим состоянием ядра;
  - сделать source/runtime divergence наблюдаемой через встроенные CLI commands;
  - ужесточить cache format contract через type/version metadata;
  - сохранить `cache:clear` как реальный recovery path, а не декларацию.

## Key Findings
- Provider ownership остался в целом чистым, но `ConsoleCommandsProvider` требовал более явного закрепления ответственности, чтобы не стать “корзиной для всего CLI”.
- `config:show` нельзя строить как прямой `json_encode($config->all())`, потому что source config допускает `Closure` и object values; для deterministic JSON нужен отдельный inspection normalizer.
- `route:list` нельзя строить через cache export path, потому что export contract намеренно запрещает `Closure` handlers, а observability contract должен работать и в uncached source mode.
- Cache hardening без recovery special-case ломал бы собственный recovery path: несовместимый `config.php` cache snapshot блокировал бы запуск `cache:clear`.

## Decisions
- Добавлены internal observability commands:
  - `config:show [path] [--source]`
  - `route:list [--source]`
- `config:show`:
  - по умолчанию читает runtime view;
  - `--source` читает source-of-truth;
  - path optional и работает как dotted key;
  - missing path возвращает exit code `1`;
  - вывод всегда deterministic JSON text через explicit normalization mixed values.
- `route:list`:
  - по умолчанию читает runtime route view;
  - `--source` обходит route cache;
  - сохраняет registration order;
  - показывает effective methods, включая `HEAD` для `GET`.
- Введён versioned cache metadata contract:
  - config cache хранит metadata в `_framework.cache`;
  - route cache хранит top-level `cache` metadata и `index` payload;
  - loaders валидируют type/version до использования snapshot.
- Для `cache:clear` добавлен recovery bootstrap path на source config через `ConsoleApplicationFactory::createRecoveryRuntime()` и `bootstrap/console.php`.

## What This Changes
- Runtime/cache model стала наблюдаемой изнутри самого framework, а не только через тесты и ручное чтение файлов.
- Cache payload shape теперь имеет явный compatibility contract; несовместимый snapshot ломается детерминированно и рано.
- Recovery semantics стали operationally honest: `cache:clear` реально работает даже при incompatible config cache.
- Roadmap и architecture docs больше не отстают от текущего состояния ядра.

## Changed Files
- `src/Console/Internal/ConfigShowCommand.php`
- `src/Console/Internal/RouteListCommand.php`
- `src/Foundation/Bootstrap/Provider/ConsoleCommandsProvider.php`
- `src/Foundation/Bootstrap/FrameworkCacheMetadata.php`
- `src/Config/ProjectConfigLoader.php`
- `src/Config/ConfigCacheCompiler.php`
- `src/Foundation/Bootstrap/RoutesFileLoader.php`
- `src/Routing/RouteCacheCompiler.php`
- `src/Routing/RouteIndex.php`
- `src/Routing/Route.php`
- `src/Foundation/ConsoleApplicationFactory.php`
- `bootstrap/console.php`
- `tests/Foundation/CacheCommandsIntegrationTest.php`
- `tests/Foundation/Bootstrap/ConsoleProviderIntegrationTest.php`
- `README.md`
- `docs/framework-architecture.md`
- `artifacts/refactoring-plan.md`

## Validation
- `C:\OSPanel\modules\PHP-8.4\php.exe qa.php qa`: passed
- `C:\OSPanel\modules\PHP-8.4\php.exe vendor/bin/phpunit --configuration phpunit.xml.dist`: passed
- `phpunit`: `135` tests, `446` assertions
- Targeted calibration before final QA:
  - `tests/Foundation/CacheCommandsIntegrationTest.php`: passed
  - `tests/Foundation/Bootstrap/ConsoleProviderIntegrationTest.php`: passed

## Open Risks
- `config:show` runtime view честно показывает runtime config, а значит при cached config может включать framework metadata `_framework`; это правильно operationally, но требует явного понимания пользователем.
- `cache:clear` recovery special-case живёт в CLI bootstrap path; если в будущем console entrypoint будет реорганизован, этот инвариант нужно сохранить явно.
- Cache format versioning теперь существует как real compatibility seam; следующий incompatible change нельзя делать как “обычный refactor”.

## Next Actions
- Не добавлять новую тяжёлую capability до нового measured signal.
- Использовать `config:show` и `route:list` как основной observability layer при дальнейших bootstrap/runtime изменениях.
- Следующий сильный кандидат только после нового сигнала:
  - либо `container:debug`;
  - либо controlled cache format evolution;
  - но не platform-heavy growth.
