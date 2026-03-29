# 2026-03-29 Next Step After Cache Refactor

## Context
- После завершения refactor шага с `RouteIndex`, `config:cache`, `route:cache`, `cache:clear` и split validation/apply для configured services нужно определить следующий шаг развития ядра.
- Основания для решения:
  - последний шаг уже изменил bootstrap/runtime model, а не только локальную реализацию;
  - perf bottleneck routing hot path снят;
  - теперь главный риск сместился из raw performance в architectural drift и hidden operational complexity вокруг cached/runtime state.

## Key Findings
- Следующий шаг не должен быть ещё одной тяжёлой capability вроде events, autowiring или full container compilation.
- После ввода cache layer у системы появился новый класс риска:
  - divergence между `source-of-truth` и runtime snapshot;
  - постепенное расползание shared bootstrap между HTTP и CLI;
  - рост internal provider/command layer в сторону скрытого mini-framework внутри framework.
- Самая недооценённая потребность после такого шага — observability:
  - пользователю и ядру теперь важнее видеть, что реально собрано;
  - иначе cache contracts будут формально корректны, но плохо реконструируемы и хуже отлаживаемы.

## Decisions
- Непосредственно следующим шагом считать architectural checkpoint, а не новую runtime capability.
- Цель checkpoint:
  - проверить, остался ли shared bootstrap действительно shared;
  - проверить, не появилась ли лишняя сложность в provider lifecycle;
  - проверить, осталась ли app model простой и реконструируемой.
- Если checkpoint зелёный, следующим implementation target считать read-only observability commands поверх уже существующего CLI axis:
  - `route:list`
  - `config:show`
  - опционально позже `container:debug`
- `container:debug` не делать первым:
  - он требует аккуратнее формализовать, что именно считается стабильной наблюдаемой моделью контейнера;
  - цена ошибки там выше, чем у `route:list` и `config:show`.

## What This Changes
- Приоритет смещается с optimization-first мышления на observability-first мышление.
- Следующий этап должен не расширять platform surface любой ценой, а сделать уже появившиеся cache/runtime contracts видимыми и проверяемыми.
- Roadmap нужно синхронизировать: старый `artifacts/refactoring-plan.md` ещё не отражает завершённый cache/runtime phase и текущую точку принятия решений.

## Next Actions
- Провести architectural checkpoint по bootstrap/runtime после cache refactor.
- Синхронизировать `artifacts/refactoring-plan.md` с фактическим состоянием ядра.
- Если checkpoint не выявит серьёзного drift:
  - реализовать `route:list` на базе `RouteRegistry`/`RouteIndex`;
  - реализовать `config:show` с явным различением `source` и `runtime` view.
- Отложить:
  - full compiled container;
  - autowiring;
  - public provider API;
  - events;
  - тяжёлые HTTP subsystems.

## Changed Files
- `artifacts/execution/2026-03-29-next-step-after-cache-refactor.md`

## Validation
- Локальная аналитическая сверка проведена по:
  - `artifacts/refactoring-plan.md`
  - `artifacts/execution/2026-03-29-bootstrap-runtime-cache-and-route-index.md`
  - `tools/perf/benchmark.php`
- Код не менялся; PHP QA-контур для этой записи неприменим как отдельная проверка.

## Open Risks
- `artifacts/refactoring-plan.md` устарел относительно текущего состояния ядра и требует обновления, иначе roadmap перестанет быть надёжной картой решений.
- Если пропустить checkpoint и сразу идти в новые capability, есть риск закрепить hidden drift в bootstrap/runtime layer.
