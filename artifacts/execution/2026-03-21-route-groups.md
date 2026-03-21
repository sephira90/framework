# 2026-03-21 Route Groups

## Context
- После named routes естественным следующим расширением routing model были route groups.
- Требование было жёстким: добавить prefix и middleware inheritance, не создавая новую runtime subsystem, новый provider layer или дополнительную фазу bootstrap lifecycle.

## Key Findings
- Route groups лучше реализуются как registration-time state внутри `RouteCollector`, а не как отдельный объект в runtime graph.
- Если group state не восстанавливать жёстко, collector начнёт протекать между соседними registrations. Поэтому здесь критичен `try/finally`, а не просто push/pop “по договорённости”.
- Основной инвариант groups:
  - outer prefix + inner prefix + route path складываются в один canonical route path;
  - middleware order наследуется как outer → inner → route-level.

## Decisions
- Добавлен `RouteCollector::group(string $prefix, callable $registrar, array $middleware = []): void`.
- Внутри collector заведены два registration-time state holder'а:
  - текущий group prefix;
  - текущий inherited middleware stack.
- Route registration теперь собирает effective path и effective middleware через этот текущий state.
- Group state всегда откатывается через `try/finally`, даже если registrar выбросит исключение.

## Changed Files
- `src/Routing/RouteCollector.php`
- `tests/Routing/RouteCollectorTest.php`
- `tests/Foundation/ApplicationFactoryTest.php`
- `README.md`
- `docs/framework-architecture.md`
- `artifacts/refactoring-plan.md`

## Validation
- `C:\OSPanel\modules\PHP-8.4\php.exe vendor\bin\phpunit --configuration phpunit.xml.dist tests\Routing\RouteCollectorTest.php tests\Foundation\ApplicationFactoryTest.php`: passed
- `C:\OSPanel\modules\PHP-8.4\php.exe C:\OSPanel\data\PHP-8.4\default\composer\composer.phar qa`: passed
- `lint`: passed
- `phpcs`: passed
- `phpstan` level `10`: passed
- `psalm`: passed
- `C:\OSPanel\modules\PHP-8.4\php.exe C:\OSPanel\data\PHP-8.4\default\composer\composer.phar test`: passed
- `phpunit`: passed (`74` tests, `199` assertions)

## What This Changes
- Routing subsystem теперь поддерживает четыре согласованные registration-time capability:
  - plain route registration;
  - fluent route naming;
  - URL generation;
  - nested route groups.
- Group metadata не создаёт отдельный runtime entity: после регистрации в системе остаются обычные `Route` instances с уже собранными path и middleware.

## Open Risks
- `RouteCollector` теперь хранит чуть больше mutable registration state, чем раньше.
- Это допустимо, пока state строго локализован в routes file execution и всегда восстанавливается deterministic способом.

## Next Actions
- Следующая capability phase: `HttpException hierarchy`.
- Отдельный measured debt остаётся прежним: revisit `ContainerBuilder` simplification только при suppression-free решении.
