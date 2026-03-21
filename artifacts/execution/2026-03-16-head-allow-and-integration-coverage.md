# 2026-03-16 HEAD Allow and Integration Coverage

## Context
- После закрытия transport-level `HEAD` body suppression в системе оставались два связанных зазора:
  - `405 Method Not Allowed` не отражал implicit `HEAD` support при наличии `GET`;
  - не было integration-покрытия полного пути `HEAD -> application -> emitter`.
- Это был не новый feature, а доведение уже выбранной `HEAD` модели до консистентного HTTP поведения.

## Key Findings
- Семаника `HEAD` в текущем ядре живёт на двух уровнях:
  - routing разрешает `HEAD` через explicit route или `GET` fallback;
  - transport boundary не отправляет body для `HEAD`.
- Пока `Allow` не включал `HEAD` при наличии `GET`, система была внутренне непротиворечива в routing, но неполна в HTTP contract surface.
- Покрытие тоже было асимметричным:
  - Router и ResponseEmitter тестировались по отдельности;
  - end-to-end связка через `HttpRuntime` не была зафиксирована.

## Decisions
- Канонизация списка `allowedMethods` перенесена в `RouteMatch::methodNotAllowed()`:
  - если в списке есть `GET`, туда автоматически добавляется `HEAD`;
  - это удерживает RFC-aware semantics в одном месте, а не размазывает её по вызывающим слоям.
- В `ApplicationFactoryTest` добавлен integration-тест:
  - создаёт runtime;
  - отправляет `HEAD` request на `GET` route;
  - проверяет `200 OK`;
  - подтверждает, что response body существует на уровне application response;
  - подтверждает, что emitter не отправляет его в output.

## Changed Files
- `src/Routing/RouteMatch.php`
- `tests/Routing/RouterTest.php`
- `tests/Http/RouteDispatcherTest.php`
- `tests/Foundation/ApplicationFactoryTest.php`
- `docs/framework-architecture.md`
- `artifacts/refactoring-plan.md`

## Validation
- `C:\OSPanel\modules\PHP-8.4\php.exe C:\OSPanel\data\PHP-8.4\default\composer\composer.phar test`: passed
- `phpunit`: passed (`65` tests, `173` assertions)
- `C:\OSPanel\modules\PHP-8.4\php.exe C:\OSPanel\data\PHP-8.4\default\composer\composer.phar qa`: passed
- `lint`: passed
- `phpcs`: passed
- `phpstan` level `10`: passed
- `psalm`: passed

## What This Changes
- `HEAD` semantics теперь консистентны на трёх слоях:
  - matching;
  - `405 Allow`;
  - transport emission.
- Execution plan больше не должен считать `HEAD Allow` и `HEAD integration coverage` открытыми задачами.

## Next Actions
- Следующий активный pressure point: решить явный контракт нормализации path между `Router` и `Route`.
- После этого добрать low-risk quality fixes:
  - `new $class()` вместо `ReflectionClass::newInstance()`;
  - accessor-методы для `ServiceDefinition`.
