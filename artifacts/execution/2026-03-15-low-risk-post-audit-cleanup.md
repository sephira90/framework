# 2026-03-15 Low-Risk Post-Audit Cleanup

## Context
- После критического разбора post-`v0 hardening` аудита был выбран безопасный пакет улучшений без смены публичной модели framework.
- В пакет вошли четыре пункта:
  - optional boot contract для providers;
  - перенос формы factory invocation в build-time;
  - scope isolation для `require` в loaders;
  - выравнивание доступа к `Config` внутри `CoreServicesProvider`.

## Key Findings
- Единый `ServiceProviderInterface` действительно был шире реальной потребности: два providers не использовали boot phase.
- Reflection в контейнере действительно не должен был жить в runtime path, если форма factory может быть проверена при registration.
- `require` в instance-method loader действительно создавал лишнюю поверхность scope leakage.
- Захват `Config` через `use ($config)` в одном factory ломал единообразие container-managed assembly.

## Decisions
- `ServiceProviderInterface` сужен до `register()`.
- Добавлен `BootableProviderInterface` для providers, которым реально нужен post-build boot.
- `Bootstrapper` теперь вызывает `boot()` только для bootable providers.
- `ServiceDefinition` расширен полем `requiresContainer`, которое вычисляется в `ContainerBuilder`.
- Проверка допустимой сигнатуры service factory перенесена в build-time.
- `ConfigLoader` и `RoutesFileLoader` теперь подключают файлы через изолированный `require` helper.
- `CoreServicesProvider` больше не тянет `Config` через closure capture и резолвит его из контейнера как и остальные зависимости.

## What This Changes
- Bootstrap lifecycle стал честнее: boot теперь опциональная фаза, а не обязательный пустой ритуал.
- Container runtime упростился: reflection исчез из `get()` path и остался только в registration/build phase.
- Config/routes loading стал безопаснее с точки зрения причинной модели и локального scope.
- Runtime graph стал последовательнее: framework services читают `Config` одинаковым способом.

## Changed Files
- `src/Foundation/Bootstrap/ServiceProviderInterface.php`
- `src/Foundation/Bootstrap/BootableProviderInterface.php`
- `src/Foundation/Bootstrap/Bootstrapper.php`
- `src/Foundation/Bootstrap/Provider/CoreServicesProvider.php`
- `src/Foundation/Bootstrap/Provider/ConfiguredServicesProvider.php`
- `src/Foundation/Bootstrap/Provider/RoutingServiceProvider.php`
- `src/Foundation/Bootstrap/Provider/HttpKernelProvider.php`
- `src/Container/ServiceDefinition.php`
- `src/Container/ContainerBuilder.php`
- `src/Container/Container.php`
- `src/Config/ConfigLoader.php`
- `src/Foundation/Bootstrap/RoutesFileLoader.php`
- `tests/Foundation/Bootstrap/BootstrapperTest.php`
- `tests/Foundation/Bootstrap/ProviderIntegrationTest.php`
- `tests/Container/ContainerBuilderTest.php`
- `docs/framework-architecture.md`

## Validation
- `C:\OSPanel\modules\PHP-8.4\php.exe C:\OSPanel\data\PHP-8.4\default\composer\composer.phar qa`: passed
- `lint`: passed
- `phpcs`: passed
- `phpstan` level `10`: passed
- `psalm`: passed
- `C:\OSPanel\modules\PHP-8.4\php.exe C:\OSPanel\data\PHP-8.4\default\composer\composer.phar test`: passed
- `phpunit`: passed (`62` tests, `165` assertions)

## Open Risks
- Strict `HEAD` semantics по-прежнему не закрыты: routing уже поддерживает `HEAD -> GET`, но emission semantics пока не изменены.
- Dynamic `require` в loaders остался допустимым runtime behavior, поэтому для Psalm применён локальный suppress на `UnresolvableInclude`.
- `ContainerAccessor` и bootstrap registries по-прежнему остаются осознанными trade-offs, а не предметом этого cleanup-пакета.

## Next Actions
- Следующим bounded correctness step остаётся решение по `HEAD` body suppression.
- После этого стоит пересмотреть, нужны ли ещё low-risk cleanup changes, или следующая работа уже должна идти через новую capability/behavior phase.
