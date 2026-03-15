# 2026-03-15 Environment Loader State Reduction

## Context
- The next planned Phase 2 correctness step after `HEAD -> GET` fallback was the `EnvironmentLoader` pressure point.
- The existing implementation cached loaded base paths in a static process-level array.
- That design was cheap for short-lived PHP requests, but it made repeated bootstrap cycles in the same process depend on hidden internal state.

## Change
- Refactored `src/Config/EnvironmentLoader.php` into a stateless loader:
  - removed the static `$loadedPaths` cache;
  - changed `load()` from a static API to an instance method;
  - kept the behavior intentionally narrow: load `.env` if it exists, otherwise do nothing.
- Updated `src/Foundation/ApplicationFactory.php` to create `EnvironmentLoader` explicitly during runtime assembly.

## Added Tests
- Updated `tests/Config/ConfigTest.php` to:
  - keep the existing configuration bootstrap test on the new instance-based API;
  - add a regression test that clears environment values, rewrites the same `.env` file, and reloads the same base path in the same process.

## Documentation Update
- Updated `docs/framework-architecture.md` so it no longer describes `EnvironmentLoader` as a static bootstrap step with implicit process memory.
- The architecture document now states explicitly that the same base path can be reloaded after environment cleanup.

## Why This Matters
- The main defect was not performance; it was analyzability.
- Hidden process-level path caching made bootstrap behavior depend on prior history instead of current inputs.
- The new design is easier to reason about in tests and long-running processes because loader behavior now depends only on:
  - `basePath`;
  - presence or absence of `.env`;
  - the current environment state visible to `phpdotenv`.

## Validation
- `C:\OSPanel\modules\PHP-8.4\php.exe C:\OSPanel\data\PHP-8.4\default\composer\composer.phar qa`: passed
- `lint`: passed
- `phpcs`: passed
- `phpstan` level `10`: passed
- `psalm`: passed
- `C:\OSPanel\modules\PHP-8.4\php.exe C:\OSPanel\data\PHP-8.4\default\composer\composer.phar test`: passed
- `phpunit`: passed (`49` tests, `132` assertions)

## Open Risk
- This removes the loader's hidden cache, but `.env` loading still mutates process environment through `phpdotenv`.
- That mutation is appropriate for the current bootstrap model, but it remains a global-side-effect seam and should be revisited only if the framework later needs stricter runtime isolation or injectable environment sources.

## Next Actions
- Continue Phase 2 / measured-debt work only where there is a demonstrated signal.
- The next likely candidate is not broad structural breakup, but a tighter review of `ApplicationFactory` seams and whether any extraction is justified by cohesion rather than style.
