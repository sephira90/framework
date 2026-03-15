# План Рефакторинга: Актуальное Состояние После v0 Hardening

Дата актуализации: `2026-03-15`

## 1. Назначение документа

Этот документ больше не описывает исторический backlog `v0`.
Его задача теперь другая:

- зафиксировать, что уже реально реализовано;
- отделить закрытые пункты от живых архитектурных рисков;
- не путать deliberate scope limits с дефектами;
- показать, куда двигаться дальше без дрейфа в feature creep и premature abstraction.

Иными словами: это не список "всего, чего ещё нет", а карта текущего инженерного давления.

---

## 2. Текущее состояние ядра

На текущем этапе framework уже имеет рабочее `v0` HTTP-ядро:

- `public/index.php` -> `bootstrap/app.php` -> `ApplicationFactory::createRuntime()`;
- `.env` загружается до чтения `config/app.php`;
- runtime собирается через explicit container;
- маршруты грузятся из `routes/web.php`;
- есть global middleware pipeline и route-level middleware;
- routing поддерживает `404`, `405`, path params и `HEAD -> GET` fallback на уровне matcher;
- `ApplicationFactory` больше не собирает runtime graph вручную;
- bootstrap lifecycle проходит через internal providers:
  - `register`
  - `build container`
  - `boot`
- routes и global middleware стали явным boot state через dedicated registries;
- runtime graph теперь container-managed.

Базовая калибровка на момент этой ревизии:

- `composer qa`: зелёный;
- `composer test`: зелёный;
- baseline после последнего кодового шага: `61` tests, `163` assertions.

---

## 3. Что уже закрыто

Следующие pressure points больше не считаются активными задачами refactoring plan:

### 3.1. Test Hardening

Закрыто.

Система уже имеет отдельный слой unit/integration tests для:

- routing seams;
- container seams;
- middleware dispatch;
- handler resolution;
- response emission;
- bootstrap lifecycle;
- provider integration.

### 3.2. `HEAD -> GET` в Router

Закрыто на уровне route matching.

Что реализовано:

- explicit `HEAD` route имеет приоритет;
- если `HEAD` route отсутствует, matcher умеет fallback к `GET`;
- поведение покрыто тестами для static и dynamic routes.

### 3.3. Скрытое состояние в `EnvironmentLoader`

Закрыто.

Что изменилось:

- loader больше не держит process-level cache путей;
- повторный bootstrap зависит от текущего состояния среды, а не от истории процесса.

### 3.4. Cohesion Pressure в `ApplicationFactory`

Закрыто как структурный шаг текущего этапа.

Что изменилось:

- `ApplicationFactory` стал thin orchestrator;
- появился internal bootstrap subsystem;
- runtime assembly переведена в container-managed model;
- internal providers существуют, но не становятся public extension API.

---

## 4. Что сейчас не считается дефектом

Ниже перечислено то, чего в системе нет сознательно. Это не backlog текущего refactoring plan.

### 4.1. Нет public provider layer

Сейчас есть только internal bootstrap providers framework.

Это означает:

- приложение не управляет provider order;
- `config/app.php` не содержит `providers`;
- user-space не получает plugin-style bootstrap API.

Это ограничение осознанное, потому что на текущем масштабе public provider layer увеличил бы API surface и lifecycle complexity быстрее, чем дал бы инженерную пользу.

### 4.2. Нет autowiring by default

Контейнер остаётся explicit-only:

- `bindings`;
- `singletons`;
- `aliases`;
- zero-argument class instantiation там, где это явно безопасно.

Отсутствие autowiring здесь не "недоделка", а защита analyzability и диагностируемости.

### 4.3. Нет полноценных platform capabilities

Пока не входят в рефакторинг ядра:

- event dispatcher;
- console kernel;
- named routes и URL generation;
- route groups;
- attribute routing;
- multi-file config hierarchy;
- auth/session/CSRF stack;
- ORM, queue, scheduler.

Это уже тема post-`v0` capabilities, а не стабилизации текущего ядра.

---

## 5. Активные точки давления

Ниже перечислены реальные, ещё не закрытые pressure points. Они расположены в порядке системной важности, а не по удобству реализации.

### P1. Не дать internal provider lifecycle превратиться в скрытый второй runtime

После рефакторинга `ApplicationFactory` новая абстракция стала реальной, а не декоративной.

Плюс:

- выше связность bootstrap slices;
- лучше виден lifecycle;
- меньше ручной wiring-логики в одной точке.

Риск:

- `boot` phase может начать разрастаться;
- registries могут размножаться;
- provider interaction может стать order-sensitive сильнее, чем это видно по коду;
- система рискует получить "ещё один runtime", только уже до HTTP runtime.

Текущий принцип:

- providers остаются internal-only;
- provider order остаётся fixed;
- любое расширение этой модели должно сначала проходить проверку: это реально снижает change cost или просто прячет сложность.

Что наблюдать:

- нужен ли новый registry для каждого следующего шага;
- появляются ли cross-provider hidden dependencies;
- растёт ли число bootstrap-specific exceptions и lifecycle rules быстрее, чем польза от декомпозиции.

### P2. `HEAD` semantics закрыты не полностью

`Router` уже умеет `HEAD -> GET`, но это решает только matching semantics.

Открытый вопрос:

- должен ли runtime/emitter подавлять response body для `HEAD` автоматически.

Сейчас риск ограниченный:

- маршрутизация корректнее;
- но wire-level behavior ещё не доведено до строгой `HEAD` semantics.

Это нужно решать отдельно, чтобы не смешивать routing fix с emitter behavior change.

### P3. Reflection в контейнере остаётся measured debt, а не обязательным refactor target

В `Container::invokeFactory()` и zero-argument instantiation остаётся reflection.

Это ещё не дефект само по себе.

Слабая модель была бы такой: "раз есть reflection, надо срочно убрать".
Сильная модель другая:

- сначала нужен measured signal;
- потом benchmark или хотя бы воспроизводимый workload;
- только после этого можно говорить об architectural cost optimization.

До этого момента это не phase-defining задача.

### P4. Нужно удерживать синхронность между кодом, архитектурной документацией и планом

Последний дрейф как раз это показал: реализация уже ушла вперёд, а план остался описывать прошлое состояние.

Риск здесь не декоративный:

- неправильный roadmap искажает приоритеты;
- пользователь начинает воспринимать deliberate limits как defects;
- следующие решения принимаются по устаревшей модели системы.

Значит, для архитектурных изменений нужно поддерживать три слоя сразу:

- код;
- `docs/framework-architecture.md`;
- `artifacts/refactoring-plan.md`.

---

## 6. Актуальный план работ

### Phase A. Stabilize Internal Bootstrap Layer

Цель:

- проверить, остаётся ли provider lifecycle компактным и анализируемым;
- не допустить дрейфа к public-provider architecture без отдельного решения.

Задачи:

- наблюдать за ростом `boot` responsibilities;
- не добавлять app-level provider API;
- не вводить provider priorities, discovery или user registration без явной архитектурной фазы.

Критерий завершения:

- следующий крупный шаг в ядре можно реализовать без взрывного роста bootstrap-specific machinery.

### Phase B. Close Remaining Bounded Correctness Gaps

Цель:

- закрывать только те behavior gaps, которые реально повышают correctness текущего HTTP lifecycle.

Первый кандидат:

- принять архитектурное решение по `HEAD` body suppression:
  - либо подавляем body на emitter/runtime уровне;
  - либо фиксируем, что в `v0` поддерживается только route matching semantics без strict emission semantics.

Критерий завершения:

- поведение `HEAD` явно определено и покрыто тестами;
- не остаётся скрытой двусмысленности между router behavior и response emission.

### Phase C. Work Only on Measured Debt

Цель:

- не заниматься micro-optimization by suspicion.

Кандидаты:

- reflection cost в container;
- избыточные allocations в middleware dispatch;
- bootstrap overhead.

Правило:

- сначала измерение;
- потом гипотеза;
- потом ограниченный refactor;
- потом повторная проверка.

### Phase D. Post-v0 Capability Expansion

Это уже не текущий refactoring plan в узком смысле, а следующий архитектурный горизонт.

Сюда можно переносить только после стабилизации текущего ядра:

- named routes / URL generation;
- route groups;
- console runtime;
- eventing;
- richer config model;
- возможные app-level extension mechanisms.

---

## 7. Что делать следующим

Следующий наиболее рациональный шаг сейчас:

1. определить целевую модель `HEAD` semantics;
2. если нужен strict behavior, реализовать body suppression как bounded correctness fix;
3. если strict behavior пока не нужен, зафиксировать это как сознательное ограничение `v0` в документации и тестах.

Почему именно это:

- это последний явный correctness gap, который уже обнаружен;
- он локален;
- он не требует новой платформенной абстракции;
- он лучше подходит для текущего этапа, чем дальнейшее усложнение bootstrap subsystem.

После этого стоит вернуться к мета-вопросу:

- internal provider layer реально снижает change cost,
- или уже начинает создавать больше lifecycle complexity, чем убирает.

---

## 8. Краткий вывод

Текущий refactoring plan больше не про "дособрать `v0` с нуля".

Он теперь про другое:

- удержать уже собранное ядро архитектурно честным;
- не превратить internal bootstrap mechanism в новую скрытую платформу;
- закрыть оставшиеся bounded correctness gaps;
- не подменять measured engineering work догадками, convenience-driven расширением и feature drift.
