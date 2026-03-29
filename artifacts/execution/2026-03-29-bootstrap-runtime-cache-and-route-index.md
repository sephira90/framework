# 2026-03-29 Bootstrap Runtime Cache And Route Index

## Context
- Реализован крупный рефакторинг bootstrap/runtime по ранее согласованному плану: ускорение routing hot path, явный cache pipeline для `config` и `routes`, а также разделение configured-services registration на validation layer и apply layer.
- Основания для шага:
  - routing был главным measured bottleneck;
  - runtime не имел explicit cache model и повторно проходил source assembly на каждом запуске;
  - `ConfiguredServicesRegistrar` совмещал validation и application, что делало cached boot менее честным и хуже реконструируемым.

## Key Findings
- Наибольший practical win дал не micro-tuning container registration, а перестройка маршрутизации вокруг предкомпилированного `RouteIndex`.
- Явный cache pipeline полезен только если он честный:
  - runtime использует cache только если файл уже существует;
  - runtime ничего не перестраивает автоматически во время HTTP/CLI execution;
  - stale cache считается допустимым operational state до явного `cache:clear` или rebuild.
- Cacheability должна быть контрактом, а не best-effort магией:
  - `route:cache` отвергает `Closure` handlers и instance-based middleware;
  - `config:cache` отвергает non-exportable config values;
  - container definitions с `Closure` и object instances остаются допустимыми только в uncached mode.
- После рефакторинга perf harness показывает, что dynamic routing больше не выглядит как системный hotspot того же класса:
  - `router_dynamic_match_last_of_1000`: `0.060664ms` avg;
  - `router_dynamic_miss_1000`: `0.036518ms` avg;
  - `router_static_match_last_of_1000`: `0.023544ms` avg.

## Decisions
- Введён внутренний `RouteIndex` как предкомпилированный routing model:
  - static routes indexed directly by normalized path;
  - dynamic routes bucketed by `segmentCount` и `firstLiteralSegment`;
  - wildcard-leading routes идут в отдельные buckets;
  - order invariants сохранены через deterministic merge при match.
- `Router` оставлен thin facade поверх `RouteIndex`, чтобы public usage не усложнился, а hot path стал дешевле.
- Введён explicit framework cache directory: `var/cache/framework/`.
- Добавлены internal framework commands:
  - `config:cache`
  - `route:cache`
  - `cache:clear`
- Зарезервированы префиксы `config:*`, `route:*`, `cache:*`; коллизия с app commands считается bootstrap error.
- В конфигурационном слое введён `ProjectConfigLoader`:
  - `loadSource()` всегда читает source-of-truth;
  - `loadRuntime()` использует cache snapshot при наличии.
- Container config разделён на два слоя:
  - `ConfiguredContainerConfigValidator` строит immutable validated model;
  - `ConfiguredServicesRegistrar` только применяет уже проверенные bindings/singletons/aliases.
- Default skeleton переведён на cache-safe container definitions через exportable static factory references.

## What This Changes
- Bootstrap/runtime больше не завязан на наивную модель "каждый запуск всегда собирает всё из исходников".
- Routing core перестал платить линейную цену за все dynamic routes в обычном hot path.
- Cache contracts стали явной частью архитектуры:
  - что можно кэшировать;
  - что нельзя;
  - когда runtime использует snapshot;
  - как система ведёт себя при устаревшем cache state.
- Container registration стал лучше декомпозирован:
  - validation можно переиспользовать в `config:cache`;
  - cached runtime не обязан заново проходить полную shape-validation по каждому entry.

## Changed Files
- `src/Routing/RouteIndex.php`
- `src/Routing/Route.php`
- `src/Routing/CompiledRoutePath.php`
- `src/Routing/Router.php`
- `src/Routing/RouteCacheCompiler.php`
- `src/Config/ProjectConfigLoader.php`
- `src/Config/ConfigCacheCompiler.php`
- `src/Foundation/ApplicationFactory.php`
- `src/Foundation/ConsoleApplicationFactory.php`
- `src/Foundation/Bootstrap/RoutesFileLoader.php`
- `src/Foundation/Bootstrap/RouteRegistry.php`
- `src/Foundation/Bootstrap/FrameworkCachePaths.php`
- `src/Foundation/Bootstrap/CacheFileWriter.php`
- `src/Foundation/Bootstrap/ConfiguredContainerConfig.php`
- `src/Foundation/Bootstrap/ConfiguredContainerConfigValidator.php`
- `src/Foundation/Bootstrap/ConfiguredServicesRegistrar.php`
- `src/Foundation/Bootstrap/Provider/ConfiguredServicesProvider.php`
- `src/Foundation/Bootstrap/Provider/ConsoleCommandsProvider.php`
- `src/Console/Internal/ConfigCacheCommand.php`
- `src/Console/Internal/RouteCacheCommand.php`
- `src/Console/Internal/CacheClearCommand.php`
- `config/container.php`
- `app/Support/AppServiceFactory.php`
- `tools/perf/benchmark.php`
- `tests/Routing/RouterTest.php`
- `tests/Foundation/Bootstrap/RouteRegistryTest.php`
- `tests/Foundation/Bootstrap/ConsoleProviderIntegrationTest.php`
- `tests/Foundation/CacheCommandsIntegrationTest.php`
- `README.md`
- `docs/framework-architecture.md`

## Validation
- `C:\OSPanel\modules\PHP-8.4\php.exe qa.php qa`: passed
- `C:\OSPanel\modules\PHP-8.4\php.exe vendor/bin/phpunit --configuration phpunit.xml.dist`: passed
- `phpunit`: `129` tests, `388` assertions
- `tools/perf/benchmark.php all`: passed
- Perf calibration snapshot:
  - `config_load_runtime`: `0.271410ms` avg
  - `http_create_runtime`: `1.627975ms` avg
  - `console_create_runtime`: `1.166594ms` avg
  - `configured_services_register_runtime_config`: `0.121287ms` avg
  - `router_dynamic_match_last_of_1000`: `0.060664ms` avg
  - `router_dynamic_miss_1000`: `0.036518ms` avg

## Open Risks
- `config:cache` хранит framework metadata в `_framework`; это осознанный internal contract, но он теперь входит в cache format и требует аккуратности при будущих изменениях.
- Route cache сознательно не поддерживает `Closure` handlers и instance middleware; это не баг, а operational restriction cached mode.
- Полная container graph compilation не реализована и осознанно отложена; текущий этап ограничен cache-friendly validation/application split.
- Perf harness даёт reproducible local calibration, но не заменяет real-world profiling под PHP-FPM, autoload pressure и larger application graphs.

## Next Actions
- Проверить, нужен ли следующий measured step в container/runtime path, или текущий bottleneck уже сместился в другие зоны.
- При дальнейшем росте framework держать cache format versioning в уме, если появятся несовместимые изменения export shape.
- Не расширять cached surface без новых измерений: сначала новый signal, потом новая сложность.
