# 2026-03-21 Named Routes and URL Generation

## Context
- После стабилизации `v0` и закрытия ближайших correctness / quality gaps следующей сильной capability phase был выбран локальный routing growth:
  - named routes;
  - URL generation.
- Критерий выбора: высокая практическая ценность без новой execution axis. Изменение должно было остаться внутри `src/Routing`, не таща новые providers, config keys или runtime modes.

## Key Findings
- Named routes лучше всего ложатся не как отдельный registry outside routing, а как optional invariant самого `Route`.
- URL generation симметрична уже существующему path contract:
  - route хранит path pattern и parameter names;
  - router индексирует named routes и даёт lookup surface.
- Fluent API требует отдельного компромисса:
  - registration route может остаться без имени;
  - поэтому возвращаемый `RouteBuilder` из `RouteCollector` по дизайну optional;
  - Psalm трактует часть таких return values как `PossiblyUnusedReturnValue`, поэтому для методов collector добавлены точечные suppressions с явной причиной.

## Decisions
- Добавлен `RouteBuilder` как thin post-registration layer:
  - route регистрируется сразу;
  - builder только добавляет optional metadata поверх уже зарегистрированного immutable `Route`.
- `Route` расширен на:
  - optional `name`;
  - `withName(string $name): self`;
  - `generatePath(array $parameters = []): string`.
- `Router` расширен на:
  - `namedRoutes` index;
  - `url(string $name, array $parameters = []): string`.
- Duplicate route names считаются invariant violation и ломают сборку `Router` fail-fast.

## Changed Files
- `src/Routing/Route.php`
- `src/Routing/RouteCollection.php`
- `src/Routing/RouteCollector.php`
- `src/Routing/RouteBuilder.php`
- `src/Routing/Router.php`
- `tests/Routing/RouteTest.php`
- `tests/Routing/RouteCollectorTest.php`
- `tests/Routing/RouterTest.php`
- `README.md`
- `docs/framework-architecture.md`
- `artifacts/refactoring-plan.md`

## Validation
- `C:\OSPanel\modules\PHP-8.4\php.exe vendor\bin\phpunit --configuration phpunit.xml.dist tests\Routing\RouteTest.php tests\Routing\RouteCollectorTest.php tests\Routing\RouterTest.php`: passed
- `C:\OSPanel\modules\PHP-8.4\php.exe C:\OSPanel\data\PHP-8.4\default\composer\composer.phar qa`: passed
- `lint`: passed
- `phpcs`: passed
- `phpstan` level `10`: passed
- `psalm`: passed
- `C:\OSPanel\modules\PHP-8.4\php.exe C:\OSPanel\data\PHP-8.4\default\composer\composer.phar test`: passed
- `phpunit`: passed (`71` tests, `191` assertions)

## What This Changes
- Routing subsystem теперь поддерживает две симметричные операции:
  - inbound matching;
  - outbound path generation.
- Named routes больше не считаются roadmap item или deliberate non-goal.
- Следующая capability phase естественно сдвигается к `RouteCollector::group()` и наследованию prefix / middleware.

## Open Risks
- Fluent API опирается на точечные Psalm suppressions в `RouteCollector`, потому что возвращаемый builder намеренно optional.
- Это не ломает correctness, но остаётся местом, где DX API и статический профиль не совпадают идеально.

## Next Actions
- Следующая capability phase: `Route groups`.
- Отдельный measured debt: revisit `ContainerBuilder` simplification только при suppression-free решении.
