# 2026-03-14 v0 HTTP Kernel

## Context
- Implemented the agreed `v0` minimal HTTP kernel.
- Goal: make the framework end-to-end runnable with explicit boundaries from request creation to response emission.

## Key Findings
- The smallest honest kernel still needs explicit seams for config, container, routing, middleware, handler resolution, error conversion, and runtime bootstrap.
- Static analysis exposed two real pressure points early: weakly typed config maps and the `require` boundary in `public/index.php`.
- PHPUnit discovery is reflective, so test classes needed explicit local Psalm suppressions for `UnusedClass`; this is tool noise, not an architectural defect.

## Decisions
- Kept the public HTTP boundary PSR-first with `PSR-7`, `PSR-15`, and `PSR-11`.
- Chose `nyholm/psr7` and `nyholm/psr7-server` instead of building a custom PSR-7 stack in `v0`.
- Kept the container explicit: bindings, singletons, aliases, zero-argument class instantiation, no reflection autowiring.
- Implemented router behavior with static-route priority, path parameters, `404`, and `405`.
- Added a single error boundary through `ErrorHandlingMiddleware` and `ErrorResponseFactory`.
- Added a minimal app skeleton with `bootstrap/app.php`, `config/app.php`, `routes/web.php`, and `public/index.php`.

## What This Changes
- The repository now contains a real runnable framework core instead of only tooling and project rules.
- `ApplicationFactory::createRuntime()` can assemble a working runtime from config and routes.
- Requests now pass through a full lifecycle: globals/request creation -> middleware pipeline -> router -> handler resolution -> response -> emitter.
- The project has regression coverage for config loading, environment bootstrap, container behavior, routing, middleware order, error handling, and HTTP end-to-end flow.

## Changed Files
- `composer.json`
- `composer.lock`
- `app/`
- `bootstrap/`
- `config/`
- `public/`
- `routes/`
- `src/`
- `tests/`
- `artifacts/execution/2026-03-14-v0-http-kernel.md`

## Validation
- `composer qa`: passed
- `parallel-lint`: passed
- `phpcs`: passed
- `phpstan` level `10`: passed
- `psalm`: passed
- `composer test`: passed
- `phpunit`: passed (`15` tests, `56` assertions)

## Open Risks
- The container is intentionally minimal and will not satisfy handlers or middleware with constructor dependencies unless they are registered explicitly.
- The runtime is HTTP-only and synchronous; CLI kernel, events, sessions, templating, persistence, and package discovery are still consciously out of scope.
- `public/index.php` contains a targeted Psalm suppression to reconcile a real cross-tool disagreement on the `require` bootstrap boundary.

## Next Actions
- Freeze the `v0` surface as the architectural baseline.
- Start the next iteration with one extension seam at a time: either richer route metadata, better config composition, or more ergonomic service registration.
