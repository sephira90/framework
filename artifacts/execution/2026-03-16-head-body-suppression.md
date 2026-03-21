# 2026-03-16 HEAD Body Suppression

## Context
- Следующим bounded correctness step после `v0 hardening` и low-risk cleanup был выбор по strict `HEAD` semantics.
- Router уже поддерживал `HEAD -> GET`, но transport boundary всё ещё отправляла response body, что делало HTTP behavior неполным.

## Key Findings
- Правильный слой исправления находится на transport boundary, а не в middleware pipeline:
  - routing уже решает matching semantics;
  - middleware не должен брать на себя transport-specific emission policy;
  - emitter может уметь не писать body, но решение о такой политике должно приниматься там, где одновременно видны request и response.
- В текущей архитектуре этим слоем естественно выступает front controller:
  - он уже создаёт request;
  - получает response;
  - управляет final emission.

## Decisions
- `ResponseEmitter` расширен параметром `emitBody`, который управляет только transport-level body output.
- `public/index.php` теперь:
  - создаёт request;
  - получает response;
  - подавляет body emission для `HEAD` через `emitBody: false`.
- Middleware, router и application/kernel не менялись.

## What This Changes
- `HEAD` теперь имеет завершённую transport semantics в рамках текущего `v0`.
- Framework не смешивает:
  - route matching;
  - request handling;
  - final transport emission policy.
- Закрыт последний явно зафиксированный correctness gap текущего HTTP lifecycle.

## Changed Files
- `src/Http/ResponseEmitter.php`
- `public/index.php`
- `tests/Http/ResponseEmitterTest.php`
- `docs/framework-architecture.md`
- `artifacts/refactoring-plan.md`

## Validation
- `C:\OSPanel\modules\PHP-8.4\php.exe C:\OSPanel\data\PHP-8.4\default\composer\composer.phar qa`: passed
- `lint`: passed
- `phpcs`: passed
- `phpstan` level `10`: passed
- `psalm`: passed
- `C:\OSPanel\modules\PHP-8.4\php.exe C:\OSPanel\data\PHP-8.4\default\composer\composer.phar test`: passed
- `phpunit`: passed (`63` tests, `167` assertions)

## Open Risks
- `ResponseEmitter` по-прежнему не реализует более широкий no-body policy для всех status-code-driven случаев (`1xx`, `204`, `304`).
- Это не блокирует текущий `HEAD` fix, но может стать следующим transport-level hardening step, если появится явный сигнал.

## Next Actions
- Сделать короткий checkpoint по internal bootstrap layer после закрытия `HEAD` gap.
- Затем выбрать первую post-`v0` capability phase; рекомендуемый кандидат — `named routes + URL generation`.
