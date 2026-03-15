# 2026-03-15 Phase 1 Test Hardening

## Context
- User requested to continue moving according to `artifacts/refactoring-plan.md`.
- The plan explicitly prioritizes Phase 1 first: strengthen isolated test calibration before behavioural or structural refactoring.

## Decision
- Implemented the missing unit-test layer for the seams listed in Phase 1 instead of jumping ahead to `HEAD -> GET` or `EnvironmentLoader` changes.
- Kept architecture unchanged; this step is calibration, not redesign.

## Added Tests
- `tests/Routing/RouteTest.php`
- `tests/Container/ContainerBuilderTest.php`
- `tests/Http/HandlerResolverTest.php`
- `tests/Http/MiddlewareResolverTest.php`
- `tests/Http/MiddlewareDispatcherTest.php`
- `tests/Http/RouteDispatcherTest.php`
- `tests/Http/ErrorResponseFactoryTest.php`
- `tests/Http/ResponseEmitterTest.php`
- `tests/Config/EnvTest.php`

## What Is Now Covered
- `Route`
  - path normalization
  - static vs dynamic route behavior
  - parameter extraction
  - duplicate parameter rejection
- `ContainerBuilder`
  - unknown class rejection
  - non-shared object rejection
  - required-constructor rejection through built container
  - alias registration before target registration
- HTTP layer
  - handler resolution
  - middleware resolution
  - middleware ordering and short-circuiting
  - route dispatch 404 / 405 / matched-route behavior
  - error response construction
  - response emission for status/body streaming
- `Env`
  - `$_ENV` precedence
  - fallback to `$_SERVER`
  - fallback to `getenv()`
  - boolean parsing behavior

## Why This Matters
- The framework now has isolated checks around the main behavioral seams of the current `v0` core.
- This reduces dependence on only coarse-grained end-to-end tests.
- It creates a safer baseline for the next planned step: Phase 2 correctness fixes (`HEAD -> GET`, then `EnvironmentLoader` pressure point).

## Validation
- `C:\OSPanel\modules\PHP-8.4\php.exe C:\OSPanel\data\PHP-8.4\default\composer\composer.phar qa`: passed
- `lint`: passed
- `phpcs`: passed
- `phpstan` level `10`: passed
- `psalm`: passed
- `C:\OSPanel\modules\PHP-8.4\php.exe C:\OSPanel\data\PHP-8.4\default\composer\composer.phar test`: passed
- `phpunit`: passed (`46` tests, `122` assertions)

## Next Actions
- Move to Phase 2.
- First correctness target: implement `HEAD -> GET` fallback in `Router` with matching unit tests.
- After that, address `EnvironmentLoader` process-level mutable state with the minimum sufficient change.
