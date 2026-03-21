# 2026-03-21 Container Definition Consistency

## Context
- После закрытия routing path contract следующим low-risk пакетом были два container cleanup пункта:
  - убрать public properties из `ServiceDefinition`;
  - пересмотреть `ReflectionClass::newInstance()` в `ContainerBuilder`.
- Целью был не новый capability, а снижение локального conceptual noise внутри container internals.

## Key Findings
- `ServiceDefinition` действительно был простым immutable value object, но прямой доступ к public properties выбивался из общего стиля ядра.
- Попытка заменить `ReflectionClass::newInstance()` на `new $class()` локально выглядит проще, но не проходит наш quality bar:
  - runtime работает корректно;
  - PHPUnit остаётся зелёным;
  - но Psalm требует либо suppression, либо дополнительный workaround, который съедает выигрыш простоты.
- Для этого проекта "чуть проще код, но хуже analyzability" — плохой обмен.

## Decisions
- `ServiceDefinition` переведён на закрытые свойства с accessor-методами:
  - `factory()`
  - `isShared()`
  - `requiresContainer()`
- `Container` обновлён на чтение definition через этот контракт.
- Упрощение `ContainerBuilder::instantiateClass()` до `new $class()` сознательно не принято.
  - Пункт оставлен открытым как accepted low-priority debt.
  - Возвращаться к нему имеет смысл только при suppression-free реализации.

## Changed Files
- `src/Container/ServiceDefinition.php`
- `src/Container/Container.php`
- `artifacts/refactoring-plan.md`

## Validation
- `C:\OSPanel\modules\PHP-8.4\php.exe vendor\bin\phpunit --configuration phpunit.xml.dist tests\Container\ContainerBuilderTest.php tests\Container\ContainerTest.php`: passed
- `C:\OSPanel\modules\PHP-8.4\php.exe C:\OSPanel\data\PHP-8.4\default\composer\composer.phar test`: passed
- `phpunit`: passed (`66` tests, `178` assertions)
- `C:\OSPanel\modules\PHP-8.4\php.exe C:\OSPanel\data\PHP-8.4\default\composer\composer.phar qa`: passed
- `lint`: passed
- `phpcs`: passed
- `phpstan` level `10`: passed
- `psalm`: passed

## What This Changes
- Container internals стали консистентнее без изменения semantics resolution.
- Refactoring plan теперь точнее различает:
  - реально закрытый consistency fix (`ServiceDefinition`);
  - cosmetic simplification, которая пока не проходит наш статический бар (`new $class()`).

## Next Actions
- Рациональный следующий шаг больше не в container cleanup.
- Следующая сильная capability phase: `named routes + URL generation`.
