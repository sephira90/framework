# 2026-03-14 Refactoring Plan Rewrite

## Context
- Rewrote `artifacts/refactoring-plan.md` after a critical review.
- Goal: separate real `v0` defects from measured debt and post-`v0` capabilities.

## Key Findings
- The previous version mixed corrective work, speculative optimization, and future framework features into one priority chain.
- That structure made deliberate `v0` limits look like architectural defects.
- The most dangerous drift was toward provider-heavy and autowiring-heavy evolution without proof of current need.

## Decisions
- Reframed the document around three buckets:
  - `v0 hardening`
  - `measured debt`
  - `post-v0 capabilities`
- Kept the focus on test isolation, `HEAD -> GET`, `EnvironmentLoader` state, and only then measured structural debt.
- Explicitly stated that autowiring, service providers, eventing, richer routing features, and CLI runtime are not mandatory `v0` refactoring targets.

## What This Changes
- The refactoring document is now a stricter decision tool instead of a mixed backlog.
- Priority now follows system goals: correctness and calibration first, optimization and capability expansion later.
- The plan now preserves the project’s explicit, minimal, PSR-first core as the default architectural baseline.

## Changed Files
- `artifacts/refactoring-plan.md`
- `artifacts/execution/2026-03-14-refactoring-plan-rewrite.md`

## Validation
- `composer qa`: passed
- `parallel-lint`: passed
- `phpcs`: passed
- `phpstan` level `10`: passed
- `psalm`: passed
- `composer test`: passed
- `phpunit`: passed (`15` tests, `56` assertions)

## Open Risks
- The shell still renders Cyrillic markdown with mojibake in this environment, so transport/display encoding remains an operational concern.
- The rewritten plan is stronger strategically, but it still needs to be executed step by step to prove the prioritization is correct in practice.

## Next Actions
- Use the rewritten plan as the baseline roadmap.
- Start with Phase 1 from the document: isolated unit tests for the current core seams.
