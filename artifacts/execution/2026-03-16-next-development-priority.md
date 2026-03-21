# 2026-03-16 Next Development Priority

## Context
- После `v0 hardening`, введения internal bootstrap providers и low-risk cleanup-пакета нужно определить следующий рациональный шаг развития framework.
- Вопрос рассматривался не как wishlist, а как выбор следующей инженерной фазы с наибольшей системной полезностью.

## Key Findings
- Текущее ядро уже стабильно по базовому QA-контуру и имеет рабочий HTTP lifecycle.
- Главный незакрытый correctness gap остаётся вокруг strict `HEAD` semantics:
  - routing уже поддерживает `HEAD -> GET`;
  - response emission semantics ещё не доведены до RFC-совместимого поведения.
- Internal bootstrap layer сейчас нужно скорее наблюдать, чем расширять:
  - новый provider lifecycle уже даёт выигрыш по связности;
  - но следующий шаг не должен раздувать bootstrap-specific machinery без необходимости.
- Measured debt сейчас не должен становиться источником работы без сигнала:
  - container/runtime micro-optimization не является ближайшим приоритетом.

## Decisions
- Следующим bounded engineering step считать решение по `HEAD` body suppression.
- Не переходить сразу к post-`v0` capabilities, пока не закрыт этот correctness gap или не зафиксировано сознательное ограничение `v0`.
- После решения по `HEAD` провести короткую архитектурную переоценку bootstrap layer:
  - остаётся ли он компактным;
  - не растёт ли lifecycle complexity быстрее пользы.
- Только после этого открывать первую capability phase.

## What This Changes
- Ближайший план развития остаётся сфокусированным на correctness и архитектурной дисциплине, а не на расширении feature surface.
- Следующий шаг теперь явно формулируется как:
  - либо реализовать strict `HEAD` semantics;
  - либо явно документировать, что `v0` сознательно ограничивается route matching semantics для `HEAD`.

## Next Actions
1. Спроектировать правильный слой для `HEAD` body suppression:
   - не смешивать routing semantics и emission semantics;
   - учитывать, что emitter сейчас не знает о request method.
2. Если `HEAD` закрыт корректно, сделать краткий checkpoint по bootstrap providers без автоматического продолжения refactoring.
3. После этого выбрать первую post-`v0` capability.

## Open Risks
- Неправильный выбор слоя для `HEAD` может размазать transport behavior по middleware или routing и ухудшить модель системы.
- Ранний переход к named routes, route groups, console runtime или eventing до закрытия correctness gap увеличит feature surface быстрее, чем устойчивость ядра.

## Validation
- `QA`: `N/A`
- `phpcs`: `N/A`
- `phpstan`: `N/A`
- `psalm`: `N/A`
- Причина: в этом шаге зафиксировано решение по roadmap; PHP-код и поведение runtime не менялись.
