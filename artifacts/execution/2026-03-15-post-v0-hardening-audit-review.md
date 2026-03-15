# 2026-03-15 Post-v0 Hardening Audit Review

## Context
- Был проведён критический разбор внешнего аудита после `v0 hardening`.
- Целью было не подтвердить все тезисы автоматически, а отделить:
  - реальные defects;
  - measured debt;
  - guarded trade-offs;
  - спорные рекомендации, которые могут ухудшить архитектуру.

## Key Findings
- Аудит в целом сильный и хорошо попадает в реальные pressure points, но в нескольких местах переоценивает severity или предлагает неправильный слой исправления.
- Подтверждённые и полезные пункты:
  - reflection в `Container::invokeFactory()` действительно можно убрать из runtime path, перенеся проверку формы factory в build-time;
  - пустые `boot()` с `unset($context)` в двух providers — реальный design smell;
  - `require` в `RoutesFileLoader` и `ConfigLoader` действительно выполняется в scope метода и стоит быть изолированным;
  - захват `Config` через `use ($config)` в `CoreServicesProvider` — слабая консистентность, хотя и не функциональный дефект;
  - strict `HEAD` semantics остаются незакрытым correctness gap.
- Частично подтверждённые, но переоценённые или неточно сформулированные пункты:
  - single-assignment registries действительно создают temporal dependency, но она сейчас намеренно локализована и покрыта тестами; это не "скрытая случайная ошибка", а контролируемый lifecycle contract;
  - `ContainerAccessor` не является мёртвым кодом: он компенсирует `mixed`-природу `Psr\Container\ContainerInterface::get()` и защищает provider layer от не-объектных service results;
  - двойная валидация в `GlobalMiddlewareFactory` и `MiddlewareResolver` защищает разные инварианты: shape definition на bootstrap и resolved instance contract в runtime.
- Наиболее спорная рекомендация аудита:
  - реализовывать `HEAD` body suppression через middleware — не лучший слой по умолчанию;
  - проблема лежит на стыке request-aware runtime и response emission, а не в middleware pipeline как таковом.

## Decisions
- Принять как реальные кандидаты на улучшение:
  - build-time normalization для factory invocation mode;
  - optional `BootableProviderInterface`;
  - scope isolation для `require`;
  - унификацию доступа к `Config` через контейнер;
  - отдельное архитектурное решение по strict `HEAD` semantics.
- Не принимать без дополнительного обоснования:
  - lazy-init registries вместо explicit boot state;
  - удаление `ContainerAccessor` как "лишнего" только на основании того, что сейчас большинство сервисов объектные;
  - удаление bootstrap-time middleware validation как "дубликата".

## What This Changes
- Текущий refactoring focus уточнён:
  - часть замечаний можно брать как low-risk cleanup;
  - часть нужно держать как наблюдаемые trade-offs, а не чинить немедленно;
  - часть рекомендаций из аудита не должна переходить в implementation backlog без дополнительной архитектурной проверки.
- Следующие решения нужно принимать не по принципу "всё найденное исправить", а по принципу:
  - bounded correctness first;
  - cheap clarity wins second;
  - measured debt only with explicit justification;
  - no hidden complexity swaps.

## Next Actions
- Рассмотреть low-risk пакет:
  - build-time factory invocation metadata;
  - `BootableProviderInterface`;
  - scope-isolated `require`;
  - убрать `use ($config)` в `CoreServicesProvider`.
- Отдельно спроектировать strict `HEAD` semantics в правильном слое.
- Registries и `ContainerAccessor` оставить под наблюдением до появления более сильного сигнала, чем текущий stylistic discomfort.

## Changed Files
- `artifacts/execution/2026-03-15-post-v0-hardening-audit-review.md`

## Validation
- `QA`: `N/A`
- `phpcs`: `N/A`
- `phpstan`: `N/A`
- `psalm`: `N/A`
- Причина: в этом шаге зафиксирован только результат архитектурного ревью; PHP-код не менялся.

## Open Risks
- Если low-risk cleanup пакет будет реализован без общей дисциплины, можно случайно смешать:
  - реальные correctness fixes;
  - stylistic cleanup;
  - скрытое расширение API surface.
- Вопрос о `HEAD` semantics остаётся открытым до отдельного решения по request/runtime/emitter boundary.
