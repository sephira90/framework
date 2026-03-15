# 2026-03-15 HEAD To GET Fallback

## Context
- After completing Phase 1 test hardening, the next planned correctness fix was `HEAD -> GET` fallback in `Router`.
- The goal was to close a real HTTP behavior gap without introducing broader routing or emitter redesign.

## Change
- Updated `src/Routing/Router.php` so that:
  - explicit `HEAD` routes still win;
  - `HEAD` falls back to `GET` when the path supports `GET` but not `HEAD`;
  - fallback works for both static and dynamic routes.

## Added Tests
- Extended `tests/Routing/RouterTest.php` to verify:
  - `HEAD` falls back to `GET` for static routes;
  - `HEAD` falls back to `GET` for dynamic routes with parameter extraction;
  - explicit `HEAD` routes override fallback behavior.

## Why This Matters
- This improves HTTP semantics at the routing layer.
- It is a bounded correctness fix: no new capability layer, no container changes, no bootstrap restructuring.
- It follows the refactoring plan order instead of jumping to premature structural work.

## Validation
- `C:\OSPanel\modules\PHP-8.4\php.exe C:\OSPanel\data\PHP-8.4\default\composer\composer.phar qa`: passed
- `lint`: passed
- `phpcs`: passed
- `phpstan` level `10`: passed
- `psalm`: passed
- `C:\OSPanel\modules\PHP-8.4\php.exe C:\OSPanel\data\PHP-8.4\default\composer\composer.phar test`: passed
- `phpunit`: passed (`48` tests, `130` assertions)

## Open Risk
- This change improves route matching semantics, but it does not yet add explicit response-body suppression for `HEAD` at the emitter/application level.
- If stricter `HEAD` semantics are required later, that should be handled as a separate runtime behavior decision, not silently bundled into this routing change.

## Next Actions
- Move to the remaining Phase 2 pressure point: `EnvironmentLoader` and its process-level mutable state.
- Prefer the minimum sufficient change first, then reassess whether stronger redesign is justified.
