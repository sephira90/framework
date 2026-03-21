# 2026-03-21 HttpException Hierarchy

## Context
- После стабилизации routing capabilities следующей сильной phase был controlled error model growth.
- До этого любой `Throwable` в HTTP path попадал в одну ветку: `internalServerError()`.
- Это делало систему простой, но не позволяло бизнес-коду честно выразить уже выбранную HTTP-семантику вроде `403`, `404`, `422` или controlled `405`.

## Key Findings
- Важная граница здесь не "exception vs no exception", а "контролируемая HTTP-семантика vs неожиданная runtime-ошибка".
- Если код уже знает клиентскую природу отказа, forcing его обходить error boundary и вручную строить `ResponseInterface` ухудшает причинную модель.
- При этом `HttpException` не должен становиться параллельным response mechanism:
  - он должен проходить через тот же `ErrorHandlingMiddleware`;
  - решение "как превратить это в response" должно оставаться в `ErrorResponseFactory`.

## Decisions
- В `Framework\Http\Exception` добавлена базовая иерархия controlled HTTP exceptions:
  - `HttpException`
  - `NotFoundException`
  - `ForbiddenException`
  - `UnprocessableEntityException`
  - `MethodNotAllowedException`
- `ErrorResponseFactory` получил `fromHttpException(HttpException $exception)`.
- `ErrorHandlingMiddleware` теперь различает две ветки:
  - `HttpException` -> controlled client-facing response;
  - любой другой `Throwable` -> `500 Internal Server Error`.
- `MethodNotAllowedException` нормализует список allowed methods и, как и router-driven `405`, добавляет `HEAD`, если в списке есть `GET`.

## Changed Files
- `src/Http/Exception/HttpException.php`
- `src/Http/Exception/NotFoundException.php`
- `src/Http/Exception/ForbiddenException.php`
- `src/Http/Exception/UnprocessableEntityException.php`
- `src/Http/Exception/MethodNotAllowedException.php`
- `src/Http/ErrorResponseFactory.php`
- `src/Http/Middleware/ErrorHandlingMiddleware.php`
- `tests/Http/ErrorResponseFactoryTest.php`
- `tests/Foundation/ApplicationFactoryTest.php`
- `README.md`
- `docs/framework-architecture.md`
- `artifacts/refactoring-plan.md`

## Validation
- `C:\OSPanel\modules\PHP-8.4\php.exe vendor\bin\phpunit --configuration phpunit.xml.dist tests\Http\ErrorResponseFactoryTest.php tests\Foundation\ApplicationFactoryTest.php`: passed
- `C:\OSPanel\modules\PHP-8.4\php.exe C:\OSPanel\data\PHP-8.4\default\composer\composer.phar test`: passed
- `phpunit`: passed (`76` tests, `211` assertions)
- `C:\OSPanel\modules\PHP-8.4\php.exe C:\OSPanel\data\PHP-8.4\default\composer\composer.phar qa`: passed
- `lint`: passed
- `phpcs`: passed
- `phpstan` level `10`: passed
- `psalm`: passed

## What This Changes
- Error model framework'а теперь разделяет:
  - intentional HTTP failures;
  - unexpected runtime failures.
- Бизнес-код может сигнализировать controlled HTTP outcome, не обходя router, middleware pipeline и существующий error boundary.
- Следующий capability horizon естественно смещается к configuration model, а не к ещё одному обходному error mechanism.

## Open Risks
- `HttpException` делает клиентский текст ответа явным, поэтому его сообщения нужно трактовать как публичную HTTP-поверхность, а не как диагностический лог.
- Это не дефект модели, но важная operational граница: секреты и внутренняя диагностика не должны попадать в такие исключения по привычке.

## Next Actions
- Следующая capability phase: multi-file config с merge strategy.
- Отдельный measured debt остаётся прежним: revisit `ContainerBuilder` simplification только при suppression-free решении.
