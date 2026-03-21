# 2026-03-21 Multi-File Config

## Context
- После stabilizing phases у framework уже был честный HTTP kernel, но configuration model оставалась ближе к раннему `v0`: фактически вокруг одного `config/app.php`.
- Это создавало ложную централизацию: application metadata, container definitions и HTTP settings жили в одном conceptual bucket, хотя меняются по разным причинам и с разной частотой.
- Цель шага была не добавить "больше конфигурации", а сделать configuration model более системной:
  - разделить независимые config slices;
  - сохранить deterministic bootstrap;
  - не сломать текущий public bootstrap contract.

## Key Findings
- Конфигурационный рост лучше обслуживать не новым provider API и не runtime discovery, а более честной file-system моделью внутри уже существующего bootstrap flow.
- У merge strategy должна быть явная семантика, иначе multi-file config быстро становится источником скрытой причинности.
- Для этого ядра безопаснее:
  - рекурсивно мерджить associative arrays;
  - заменять list arrays целиком;
  - применять environment overlay только после полной базовой сборки.
- `.env` остаётся обязательной предшествующей фазой: environment overlay выбирается через уже собранный `app.env`.

## Decisions
- `ConfigLoader` теперь принимает либо один PHP config file, либо `config/` directory.
- В directory mode loader:
  - собирает top-level `.php` files в отсортированном порядке;
  - мерджит их детерминированно;
  - затем опционально применяет `config/environments/<app.env>.php`.
- Root-level keys в каждом config file обязаны быть строковыми; нарушение считается `InvalidConfigurationException`.
- Project skeleton переведён на dogfooding новой модели:
  - `config/app.php`
  - `config/http.php`
  - `config/container.php`
- `ApplicationFactory` теперь загружает не `config/app.php`, а `config/`.
- Targeted `Psalm` suppressions оставлены только там, где loader сознательно переносит `mixed` config payload между merge-стадиями.

## Changed Files
- `src/Config/ConfigLoader.php`
- `src/Foundation/ApplicationFactory.php`
- `config/app.php`
- `config/http.php`
- `config/container.php`
- `tests/Config/ConfigTest.php`
- `README.md`
- `docs/framework-architecture.md`
- `artifacts/refactoring-plan.md`

## Validation
- `C:\OSPanel\modules\PHP-8.4\php.exe C:\OSPanel\data\PHP-8.4\default\composer\composer.phar test`: passed
- `phpunit`: passed (`78` tests, `217` assertions)
- `C:\OSPanel\modules\PHP-8.4\php.exe C:\OSPanel\data\PHP-8.4\default\composer\composer.phar qa`: passed
- `lint`: passed
- `phpcs`: passed
- `phpstan` level `10`: passed
- `psalm`: passed

## What This Changes
- Framework больше не зависит от single-file config model как от скрытого architectural default.
- Configuration model теперь ближе к реальным зонам ответственности:
  - app metadata;
  - HTTP settings;
  - container graph.
- При этом bootstrap остаётся линейным и реконструируемым:
  - `.env`
  - `config/`
  - providers/register/build/boot
  - routes/runtime

## Open Risks
- Merge semantics теперь стали частью архитектурного контракта. Любая будущая смена правил merge будет высокорисковым изменением совместимости.
- Environment overlays полезны, но легко превращаются в скрытую конфигурационную иерархию. Это нужно держать под контролем и не расширять без явной потребности.

## Next Actions
- Следующий шаг не должен быть автоматическим ростом capability surface.
- Сначала нужен архитектурный checkpoint: что именно после multi-file config считаем следующим системным горизонтом — `ConsoleRuntime`, events, named middleware или другой слой.
