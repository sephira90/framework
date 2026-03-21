# 2026-03-21 Path Normalization Contract

## Context
- После закрытия `HEAD` correctness следующий активный routing pressure point был не в функциональности, а в причинной модели.
- `Router::match()` уже нормализовал входной path, но `Route` затем повторял ту же операцию в `matchesPath()` и `extractParameters()`.
- Это создавало тройную нормализацию для dynamic routes и размывало границу ответственности внутри routing subsystem.

## Key Findings
- Проблема была не только в лишних вызовах `trim + preg_replace + rtrim`, а в нечётком internal contract.
- Пока и `Router`, и `Route` оба считали себя владельцами path normalization, система оставалась корректной, но менее анализируемой:
  - boundary responsibility была неявной;
  - helper-методы `Route` выглядели как defensive public API, хотя фактически служат router seam.
- Более сильная модель: нормализация происходит один раз на входе в `Router::match()`, после чего по всей внутренней цепочке течёт только канонический path.

## Decisions
- `Route` больше не нормализует path повторно внутри matching helpers.
- Matching seam переименован так, чтобы контракт был виден из сигнатуры:
  - `matchesNormalizedPath()`
  - `extractParametersFromNormalizedPath()`
- `Router` остаётся единственным владельцем path normalization для request matching.
- Покрытие обновлено в двух слоях:
  - `RouteTest` теперь проверяет helpers на уже нормализованном path;
  - `RouterTest` отдельно фиксирует, что именно `Router` нормализует пользовательский ввод перед matching.

## Changed Files
- `src/Routing/Route.php`
- `src/Routing/Router.php`
- `tests/Routing/RouteTest.php`
- `tests/Routing/RouterTest.php`
- `docs/framework-architecture.md`
- `artifacts/refactoring-plan.md`

## Validation
- `C:\OSPanel\modules\PHP-8.4\php.exe vendor\bin\phpunit --configuration phpunit.xml.dist tests\Routing\RouteTest.php tests\Routing\RouterTest.php`: passed
- `C:\OSPanel\modules\PHP-8.4\php.exe C:\OSPanel\data\PHP-8.4\default\composer\composer.phar test`: passed
- `phpunit`: passed (`66` tests, `178` assertions)
- `C:\OSPanel\modules\PHP-8.4\php.exe C:\OSPanel\data\PHP-8.4\default\composer\composer.phar qa`: passed
- `lint`: passed
- `phpcs`: passed
- `phpstan` level `10`: passed
- `psalm`: passed

## What This Changes
- Routing subsystem теперь имеет более честную границу:
  - внешний path может быть грязным;
  - после `Router::match()` path считается нормализованным фактом системы;
  - `Route` работает уже не с пользовательским вводом, а с canonical routing data.
- Refactoring plan больше не должен считать path normalization contract открытым pressure point.

## Next Actions
- Следующие low-risk quality fixes:
  - `new $class()` вместо `ReflectionClass::newInstance()` в `ContainerBuilder`;
  - accessor-методы для `ServiceDefinition`.
- После этого можно переходить к первой post-v0 capability: `named routes + URL generation`.
