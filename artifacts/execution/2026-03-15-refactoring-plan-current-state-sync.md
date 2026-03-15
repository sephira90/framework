# 2026-03-15 Refactoring Plan Current-State Sync

## Context
- `artifacts/refactoring-plan.md` отстал от реальной имплементации.
- В коде уже были реализованы:
  - Phase 1 test hardening;
  - `HEAD -> GET` fallback в `Router`;
  - снижение скрытого состояния в `EnvironmentLoader`;
  - structural refactor `ApplicationFactory` через internal bootstrap providers.
- В старом плане эти шаги всё ещё читались как будущие задачи, что искажало текущую модель проекта.

## Key Findings
- План перестал быть рабочим roadmap и начал описывать историческое состояние.
- Главный риск был не в неточном тексте как таковом, а в ложной приоритизации:
  - можно было продолжать "делать refactoring plan", хотя часть фаз уже закрыта;
  - deliberate limits можно было снова принять за defects;
  - внутренний provider layer мог начать восприниматься как незавершённый public API.

## Decisions
- Не восстанавливать старый план фрагментарно.
- Переписать `artifacts/refactoring-plan.md` целиком как current-state document.
- Явно разделить:
  - что уже закрыто;
  - что не является дефектом;
  - что остаётся активным pressure point;
  - что относится уже к post-`v0` capability expansion.

## What This Changes
- План снова синхронизирован с текущей архитектурой.
- Следующие решения теперь можно принимать от реального baseline, а не от устаревшей версии ядра.
- Фокус смещён с "догоняющего refactor" на:
  - bounded correctness;
  - контроль роста internal bootstrap layer;
  - measured debt вместо speculative cleanup.

## Changed Files
- `artifacts/refactoring-plan.md`

## Validation
- `QA`: `N/A`
- `phpcs`: `N/A`
- `phpstan`: `N/A`
- `psalm`: `N/A`
- Причина: изменялась только markdown-документация; PHP-код и runtime behavior в этом шаге не менялись.

## Open Risks
- Открытым остаётся вопрос о strict `HEAD` semantics на уровне emission/runtime.
- Internal provider lifecycle пока выглядит контролируемым, но это ещё нужно подтверждать следующими изменениями, а не декларациями.

## Next Actions
- Следующим bounded engineering step считать решение по `HEAD` body suppression.
- После этого отдельно оценить, остаётся ли internal provider layer компактным и analyzable без дрейфа к public extension API.
