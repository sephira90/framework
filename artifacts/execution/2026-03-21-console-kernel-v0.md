# Console Kernel v0

Дата: `2026-03-21`

## Что сделано

Реализован второй runtime axis: минимальный console kernel поверх уже существующего shared bootstrap layer.

Собраны следующие части:

- split `CoreServicesProvider` на:
  - `SharedServicesProvider`
  - `HttpCoreServicesProvider`
- новый `ConsoleApplicationFactory`
- новый `ConsoleRuntime`
- CLI registration model:
  - `CommandCollection`
  - `CommandCollector`
  - `CommandRegistry`
  - `CommandsFileLoader`
- CLI execution model:
  - `ArgvInputFactory`
  - `CommandInput`
  - `CommandResolver`
  - `ConsoleApplication`
  - `ConsoleOutput`
  - `ConsoleErrorRenderer`
- новый app seam:
  - `commands/console.php`
  - `config/console.php`
  - `app/Console/Command/AboutCommand.php`
- новый entrypoint:
  - `bootstrap/console.php`
  - `bin/console`

## Ключевые решения

- CLI и HTTP делят bootstrap, config и container.
- CLI и HTTP не делят runtime-specific services.
- Commands регистрируются только явно, без autodiscovery.
- Command model только class-based через `CommandInterface`.
- Parser contract intentionally narrow:
  - `command`
  - positional args
  - `--flag`
  - `--key=value`
  - `--`
- Missing / unknown command считаются boundary-level ошибками и отдаются через usage + exit code `1`.
- Неожиданный `Throwable` рендерится в `stderr`, причём детализация зависит от `app.debug`.

## Почему это архитектурно оправдано

Этот шаг не добавил декоративную CLI-подсистему рядом с HTTP.

Он проверяет более сильную архитектурную гипотезу:

- shared bootstrap действительно является shared;
- runtime-specific graph можно отделить без дублирования core invariants;
- framework способен иметь больше одного execution axis, не превращаясь в plugin platform.

## Документация синхронизирована

Обновлены:

- `README.md`
- `docs/framework-architecture.md`
- `artifacts/refactoring-plan.md`

## Валидация

- `composer qa` — passed
- `composer test` — passed
- baseline: `98 tests / 290 assertions`

## Ограничения, оставленные сознательно

В console kernel v0 пока не входят:

- short options
- interactive prompts
- ANSI styling
- command autodiscovery
- built-in help/list как отдельные framework commands

Это не дефекты текущей реализации, а границы первой версии CLI runtime.
