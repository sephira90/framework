# 2026-03-15 ApplicationFactory Bootstrap Providers

## Context
- After the `HEAD -> GET` and `EnvironmentLoader` correctness fixes, the next structural pressure point was `ApplicationFactory`.
- The goal was to reduce cohesion pressure without changing the public app model (`config/app.php` + `routes/web.php`) and without introducing a public provider API.

## Change
- Refactored `src/Foundation/ApplicationFactory.php` into a thin bootstrap orchestrator.
- Added an internal bootstrap subsystem under `src/Foundation/Bootstrap`:
  - `ServiceProviderInterface`
  - `BootstrapBuilder`
  - `BootstrapContext`
  - `Bootstrapper`
  - `RouteRegistry`
  - `GlobalMiddlewareRegistry`
  - `ContainerAccessor`
- Added fixed internal providers:
  - `CoreServicesProvider`
  - `ConfiguredServicesProvider`
  - `RoutingServiceProvider`
  - `HttpKernelProvider`
- Added focused helpers to keep providers cohesive:
  - `ConfiguredServicesRegistrar`
  - `RoutesFileLoader`
  - `GlobalMiddlewareFactory`

## Architectural Outcome
- `ApplicationFactory` no longer manually assembles `Router`, `RouteDispatcher`, `Application`, or `HttpRuntime`.
- Runtime graph is now container-managed:
  - `Router` resolves from `RouteRegistry`
  - `Application` resolves from `RouteDispatcher`, `MiddlewareResolver`, `GlobalMiddlewareRegistry`
  - `HttpRuntime` resolves from `Application`, `RequestFactory`, `ResponseEmitter`
- Routes and global middleware now become explicit boot state with single-assignment guarantees.

## Tests Added
- Added registry state tests:
  - `tests/Foundation/Bootstrap/RouteRegistryTest.php`
  - `tests/Foundation/Bootstrap/GlobalMiddlewareRegistryTest.php`
- Added lifecycle orchestration test:
  - `tests/Foundation/Bootstrap/BootstrapperTest.php`
- Added provider integration tests:
  - `tests/Foundation/Bootstrap/ProviderIntegrationTest.php`
- Extended `tests/Foundation/ApplicationFactoryTest.php` with fail-fast bootstrap cases for:
  - missing routes file
  - invalid routes registrar
  - invalid global middleware class

## Documentation Update
- Updated `docs/framework-architecture.md` to describe:
  - internal bootstrap providers
  - register/boot lifecycle
  - dedicated boot state registries
  - container-managed runtime graph
  - absence of a public provider layer
- Updated `artifacts/refactoring-plan.md` so the `ApplicationFactory` pressure point is marked as addressed and the next steps reflect the new state.

## Validation
- `C:\OSPanel\modules\PHP-8.4\php.exe C:\OSPanel\data\PHP-8.4\default\composer\composer.phar qa`: passed
- `lint`: passed
- `phpcs`: passed
- `phpstan` level `10`: passed
- `psalm`: passed
- `C:\OSPanel\modules\PHP-8.4\php.exe C:\OSPanel\data\PHP-8.4\default\composer\composer.phar test`: passed
- `phpunit`: passed (`61` tests, `163` assertions)

## Open Risk
- The internal provider lifecycle is now real, not decorative. That improves cohesion, but it also adds a new internal abstraction layer.
- If later changes start requiring provider priorities, discovery, or user registration, that would be a new architectural phase and should not be smuggled in as a small refactor.
- The current design assumes the heavy `boot` phase remains bounded to dedicated registries and does not become a second hidden runtime.

## Next Actions
- Observe whether the provider layer stays compact and analyzable as the framework grows.
- Reassess `Container::invokeFactory()` only when there is a measured performance or allocation signal.
- Do not turn internal providers into a public app-level API without a separate architectural decision.
