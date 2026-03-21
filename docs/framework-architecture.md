# Архитектура Framework v0

## 1. Назначение документа

Этот документ объясняет архитектуру, структуру каталогов, request lifecycle, bootstrap-процесс, ключевые инварианты и ограничения текущего ядра.

Его цель не просто перечислить файлы, а дать модель, по которой можно самостоятельно реконструировать поведение фреймворка.

---

## 2. Что такое текущий framework

Текущий `v0` — это минимальное синхронное HTTP-ядро.

Его задача:

- принять HTTP-запрос;
- превратить его в `ServerRequestInterface`;
- пропустить через глобальные middleware;
- сматчить маршрут;
- разрешить handler;
- применить route-level middleware;
- вернуть `ResponseInterface`;
- отправить ответ клиенту.

Это не full-stack framework. Это core, который показывает скелет веб-системы без дополнительных платформенных слоёв.

---

## 3. Системные границы

### Что входит в ядро

- bootstrap окружения;
- immutable config repository;
- explicit DI container;
- route collection и router;
- handler и middleware resolution;
- HTTP application/kernel;
- error-to-response conversion;
- SAPI response emission.

### Что не входит в ядро

- persistence;
- templates;
- console runtime;
- auth/session/security stack;
- events;
- queues;
- advanced routing features;
- autowiring by default.

Эти ограничения важны, потому что иначе можно ошибочно считать часть `v0` "незавершённой", хотя она просто intentionally narrow.

---

## 4. Карта каталогов

### `src/Config`

Отвечает за конфигурацию и окружение.

- `Config` — immutable repository поверх массива конфигурации;
- `ConfigLoader` — загружает либо один PHP config file, либо `config/` directory в изолированном scope и применяет deterministic merge strategy;
- `Env` — читает переменные окружения;
- `EnvironmentLoader` — поднимает `.env` до сборки runtime без process-level кеша путей;
- `InvalidConfigurationException` — сигнализирует о плохой конфигурации.

### `src/Container`

Отвечает за явную сборку зависимостей.

- `ContainerBuilder` — регистрирует definitions и aliases;
- `Container` — разрешает сервисы;
- `ServiceDefinition` — хранит factory, shared semantics и precomputed invocation mode;
- `ContainerException` / `NotFoundException` — ошибки контейнера.

### `src/Routing`

Отвечает за описание маршрутов и матчинг.

- `Route` — маршрут с методами, path, handler и middleware;
- `RouteCollection` — список маршрутов;
- `RouteCollector` — API регистрации маршрутов, route groups и inherited route metadata;
- `RouteBuilder` — fluent post-registration API для route metadata;
- `Router` — матчинг method + path и URL generation по имени маршрута;
- `RouteMatch` / `RouteMatchStatus` — результат матчинга;
- `RouteAttributes` — имена request-атрибутов, куда кладётся matched route и params.

### `src/Http`

Отвечает за runtime-исполнение HTTP запроса.

- `HandlerResolver` — превращает route handler definition в исполнимый handler;
- `MiddlewareResolver` — превращает middleware definition в `MiddlewareInterface`;
- `MiddlewareDispatcher` — строит pipeline;
- `RouteDispatcher` — соединяет router, route attributes и route-level middleware;
- `RouteHandler` — адаптер между route и handler resolver;
- `RequestFactory` — создаёт request из globals;
- `ResponseEmitter` — отправляет PSR-7 response в SAPI;
- `ErrorResponseFactory` — строит стандартные error responses;
- `Http\Middleware\ErrorHandlingMiddleware` — верхний error boundary;
- `Http\Exception\*` — ошибки плохих handler/middleware definitions и controlled HTTP exceptions.

### `src/Foundation`

Отвечает за сборку верхнего уровня.

- `ApplicationFactory` — тонко оркестрирует bootstrap и запрашивает готовый `HttpRuntime` из контейнера;
- `Application` — top-level HTTP kernel;
- `HttpRuntime` — упаковывает application, request factory и emitter.

### `src/Foundation/Bootstrap`

Отвечает за internal bootstrap lifecycle framework.

- `Bootstrapper` — прогоняет fixed-order `register -> build container -> boot bootable providers`;
- `ServiceProviderInterface` — внутренний контракт register phase;
- `BootableProviderInterface` — дополнительный внутренний контракт для providers, которым нужен post-build boot;
- `BootstrapBuilder` / `BootstrapContext` — pre-container и post-container contexts;
- `RouteRegistry` / `GlobalMiddlewareRegistry` — dedicated boot state с single-assignment semantics;
- `Provider\*` — fixed internal providers для core services, user config, routing и HTTP kernel graph.

### Прикладной слой

- `app/` — пользовательские handlers;
- `config/` — app configuration slices и optional environment overlays;
- `routes/` — route registration;
- `bootstrap/` — runtime bootstrap;
- `public/` — front controller.

---

## 5. Request Lifecycle: полный путь запроса

Это центральный процесс всей системы.

### Шаг 1. Вход в front controller

Файл `public/index.php`:

- создаёт runtime через bootstrap;
- получает request из globals;
- передаёт request в application;
- отдаёт response emitter'у с transport-level policy для `HEAD`.

Это самый внешний слой. Здесь нет routing logic, container logic или бизнес-логики.

### Шаг 2. Bootstrap runtime

Файл `bootstrap/app.php` подключает `vendor/autoload.php` и вызывает `ApplicationFactory::createRuntime(basePath)`.

Это означает: front controller остаётся тонким, а `ApplicationFactory` управляет только верхним bootstrap flow, не собирая runtime graph вручную.

### Шаг 3. Загрузка окружения и конфигурации

`ApplicationFactory` сначала создаёт `EnvironmentLoader` и вызывает `load(basePath)`.

Смысл:

- `.env` должен быть подгружен до чтения `config/`;
- конфигурация может использовать `Env::get()` и `Env::bool()`;
- один и тот же `basePath` можно повторно загрузить в том же процессе, если окружение между bootstrap-циклами было очищено;
- после этого `ConfigLoader` детерминированно мерджит top-level `config/*.php` и затем может применить `config/environments/<app.env>.php`.

Инвариант:

- к моменту сборки container config уже должен быть полностью определён.

### Шаг 4. Internal bootstrap lifecycle

После загрузки config `ApplicationFactory` создаёт `Bootstrapper` с fixed provider order:

1. `CoreServicesProvider`
2. `ConfiguredServicesProvider`
3. `RoutingServiceProvider`
4. `HttpKernelProvider`

Инвариант:

- порядок providers hardcoded;
- providers internal-only;
- приложение не конфигурирует provider list;
- `config/` и `routes/web.php` остаются primary app model.

### Шаг 5. Register phase

На фазе `register` providers только описывают service graph:

- `CoreServicesProvider` регистрирует `Config`, PSR-17 factory, `RequestFactory`, `ResponseEmitter`, `ErrorResponseFactory`, `ErrorHandlingMiddleware`;
- `ConfiguredServicesProvider` применяет пользовательские `bindings`, `singletons`, `aliases`;
- `RoutingServiceProvider` регистрирует `RouteRegistry` и container-managed `Router`;
- `HttpKernelProvider` регистрирует `GlobalMiddlewareRegistry`, `HandlerResolver`, `MiddlewareResolver`, `RouteDispatcher`, `Application`, `HttpRuntime`.

Инвариант:

- register phase ничего не резолвит;
- container остаётся explicit-only;
- никакого скрытого autowiring нет;
- если сервис требует constructor dependencies, нужно явное definition.

### Шаг 6. Boot phase

После `ContainerBuilder->build()` только bootable providers проходят через `boot`:

- `RoutingServiceProvider` загружает `routes/web.php`, валидирует callable registrar и инициализирует `RouteRegistry`;
- `HttpKernelProvider` строит global middleware stack из config, проверяет его форму и инициализирует `GlobalMiddlewareRegistry`.

Только после этого `ApplicationFactory` запрашивает `HttpRuntime` из контейнера.

Инвариант:

- `RouteRegistry` и `GlobalMiddlewareRegistry` single-assignment;
- чтение boot state до инициализации считается ошибкой lifecycle;
- runtime graph теперь container-managed, а не вручную собранный внутри `ApplicationFactory`.

### Шаг 7. Глобальный middleware pipeline

При вызове `Application::handle()`:

- создаётся `MiddlewareDispatcher`;
- первым глобальным middleware всегда выступает `ErrorHandlingMiddleware`, потому что он кладётся в `GlobalMiddlewareRegistry` ещё на bootstrap phase;
- затем идут пользовательские global middleware;
- fallback handler — это `RouteDispatcher`.

Смысл:

- исключения любого нижележащего слоя проходят через единую error boundary;
- routing остаётся внутренней частью pipeline, а не отдельным параллельным механизмом.

### Шаг 8. Матчинг маршрута

`RouteDispatcher`:

- вызывает `Router::match(method, path)`;
- получает `RouteMatch`.

`Router` нормализует входной path один раз на boundary routing layer и дальше
работает уже с канонической формой. `Route` не повторяет ту же нормализацию
внутри matching helpers: он принимает уже нормализованный path как часть
внутреннего контракта между слоями.

Возможны три исхода:

1. `Found`
2. `NotFound`
3. `MethodNotAllowed`

Если route не найден, `ErrorResponseFactory` строит `404` или `405`.
Для `405 Method Not Allowed` заголовок `Allow` отражает runtime semantics:
если путь поддерживает `GET`, в список также включается `HEAD`.

### Шаг 9. Привязка route к request

Если маршрут найден:

- matched `Route` кладётся в request attribute `framework.route`;
- route params кладутся в `framework.route_params`.

Это важно, потому что нижележащие handlers и middleware могут читать результат routing из самого request, не зная про `Router`.

### Шаг 10. Route-level middleware и handler

После match:

- создаётся `RouteHandler`;
- создаётся ещё один `MiddlewareDispatcher` уже для route-level middleware;
- fallback handler там — `RouteHandler`.

`RouteHandler` просто делегирует вызов в `HandlerResolver`.

### Шаг 11. Разрешение handler

`HandlerResolver` принимает либо:

- `Closure`;
- `class-string`.

Поведение:

- если definition — class-string, container должен вернуть объект;
- если объект реализует `RequestHandlerInterface`, вызывается `handle()`;
- если объект или closure callable, он вызывается как callable;
- результат обязан быть `ResponseInterface`.

Если это не так, кидается `InvalidHandlerException`.

### Шаг 12. Преобразование ошибок в response

Если нижележащий слой бросает `Framework\Http\Exception\HttpException`,
`ErrorHandlingMiddleware` считает, что клиентская HTTP-семантика уже выбрана явно,
и вызывает `ErrorResponseFactory::fromHttpException()`.

Если бросается любой другой `Throwable`, middleware считает это неконтролируемой
ошибкой исполнения и вызывает `ErrorResponseFactory::internalServerError()`.

Поведение зависит от `app.debug`:

- `false` -> короткий `500 Internal Server Error`;
- `true` -> подробный debug body с классом исключения, сообщением, файлом, строкой и trace.

### Шаг 13. Emission ответа

`ResponseEmitter`:

- выставляет HTTP status code;
- отправляет все headers;
- умеет не эмитить body, если transport boundary этого требует;
- rewind'ит body stream, если он seekable и body вообще должен эмититься;
- читает stream чанками по `8192` байт;
- выводит body.

Это последний слой. После него framework core считается завершившим request.

---

## 6. Основные инварианты подсистем

### Config

- `Config` immutable;
- dotted access не создаёт значения, только читает;
- `ConfigLoader` принимает либо один файл, либо config directory;
- top-level `config/*.php` мерджатся детерминированно в отсортированном порядке;
- associative arrays мерджатся рекурсивно, list arrays заменяются целиком, а не склеиваются;
- optional overlay из `config/environments/<app.env>.php` применяется после базовой сборки;
- конфиг не должен содержать сложную runtime-логику.

### Container

- сервис либо зарегистрирован явно, либо его нет;
- aliases разрешаются детерминированно;
- alias cycles и circular dependencies считаются ошибкой;
- singleton и transient semantics различаются явно.

### Routing

- path нормализуется;
- static routes приоритетнее dynamic routes;
- route match возвращает не `null`, а типизированный результат.

### HTTP execution

- global middleware всегда снаружи;
- route middleware всегда внутри matched route;
- handler всегда вызывается последним;
- ошибки любого слоя поднимаются к единому boundary.

### Foundation

- bootstrap-логика централизована;
- `ApplicationFactory` больше не держит ручную сборку runtime graph;
- internal providers управляют lifecycle в фиксированном порядке;
- не каждый provider обязан иметь boot phase;
- boot state хранится в dedicated registries, а не в скрытой локальной магии;
- `Application` не знает, как собирать container или routes;
- `HttpRuntime` — это уже собранный runtime, а не builder.

---

## 7. Точки расширения

В текущем `v0` точки расширения сознательно узкие:

- `config/*.php`
- `routes/web.php`
- container `bindings/singletons/aliases`
- global middleware
- route-level middleware
- handlers

Это сделано специально, чтобы система оставалась реконструируемой.

---

## 8. Ограничения и сознательные компромиссы

### Почему нет autowiring

Потому что для `v0` важнее:

- явность;
- простота диагностики;
- прозрачность причинности;
- учебная реконструируемость.

Autowiring удобнее, но скрывает больше правил.

### Почему нет public service providers

В текущей архитектуре уже есть internal bootstrap providers, но нет public provider layer для приложения.

Это сделано специально:

- lifecycle нужен framework core, но пока не должен становиться новым app-level API;
- приложение по-прежнему описывается через `config/` и `routes/web.php`;
- provider order остаётся закрытым и фиксированным, чтобы не размывать причинную модель bootstrap;
- это даёт выигрыш по связности `ApplicationFactory`, не превращая `v0` в plugin platform раньше необходимости.

### Почему `Route` хранит compiled state

Потому что в текущем масштабе выгоднее держать данные маршрута и его matching behavior вместе, чем разносить один концепт по нескольким типам.

### Почему container explicit-only

Потому что это уменьшает магию и делает цену каждой зависимости видимой в конфигурации.

---

## 9. Типичные failure modes

### Неправильный config

Если `config/` отсутствует, пуст, не читается или любой config file возвращает не массив, будет `InvalidConfigurationException`.

### Неправильный routes file

Если routes file не существует или не возвращает callable, runtime не соберётся.

### Нарушен bootstrap lifecycle

Если internal boot state читается до инициализации, container resolution завершается ошибкой lifecycle. Это защищает систему от частично собранного runtime graph.

### Не зарегистрирован handler или middleware

Если class-string не резолвится container'ом, произойдёт ошибка разрешения сервиса и в HTTP path она станет `500`.

### Handler вернул не `ResponseInterface`

Это считается ошибкой контракта и приводит к `InvalidHandlerException`.

### Middleware не реализует `MiddlewareInterface`

Это считается ошибкой контракта и приводит к `InvalidMiddlewareException`.

### Cycle в aliases или service resolution

Container обнаруживает цикл и выбрасывает `ContainerException`.

---

## 10. Как читать код, чтобы понять систему полностью

### Первый проход: архитектурный

1. `public/index.php`
2. `bootstrap/app.php`
3. `src/Foundation/ApplicationFactory.php`
4. `src/Foundation/Bootstrap/Bootstrapper.php`
5. `src/Foundation/Bootstrap/Provider/*`
6. `src/Foundation/Application.php`
7. `src/Foundation/HttpRuntime.php`

Цель:
- понять сборку runtime и верхний request flow.

### Второй проход: исполнение HTTP запроса

1. `src/Http/MiddlewareDispatcher.php`
2. `src/Http/RouteDispatcher.php`
3. `src/Http/RouteHandler.php`
4. `src/Http/HandlerResolver.php`
5. `src/Http/MiddlewareResolver.php`
6. `src/Http/ErrorResponseFactory.php`
7. `src/Http/Middleware/ErrorHandlingMiddleware.php`
8. `src/Http/ResponseEmitter.php`

Цель:
- понять фактический execution path запроса.

### Третий проход: routing

1. `src/Routing/RouteCollector.php`
2. `src/Routing/Route.php`
3. `src/Routing/RouteCollection.php`
4. `src/Routing/Router.php`
5. `src/Routing/RouteMatch.php`

Цель:
- понять, как route описывается, компилируется и матчится.

### Четвёртый проход: config и container

1. `src/Config/EnvironmentLoader.php`
2. `src/Config/Env.php`
3. `src/Config/ConfigLoader.php`
4. `src/Config/Config.php`
5. `src/Container/ContainerBuilder.php`
6. `src/Container/Container.php`
7. `src/Foundation/Bootstrap/ConfiguredServicesRegistrar.php`

Цель:
- понять, как runtime получает свои зависимости и настройки.

### Пятый проход: калибровка через тесты

1. `tests/Foundation/ApplicationFactoryTest.php`
2. `tests/Foundation/Bootstrap/ProviderIntegrationTest.php`
3. `tests/Routing/RouterTest.php`
4. `tests/Container/ContainerTest.php`
5. `tests/Config/ConfigTest.php`

Цель:
- сверить модель системы с формально зафиксированным поведением.

---

## 11. Процессы проекта

### Процесс разработки

1. определить границы изменения;
2. назвать инварианты;
3. внести изменение;
4. прогнать `composer qa`;
5. прогнать `composer test`;
6. записать результат в `artifacts/execution/`.

### Процесс чтения и обучения

1. сначала читать обзорную архитектуру;
2. затем идти по execution path;
3. затем читать tests как независимую калибровку;
4. после этого пытаться воспроизвести модель своими словами.

### Процесс принятия архитектурных решений

Каждое нетривиальное изменение нужно оценивать по вопросам:

- это исправляет дефект или добавляет capability;
- какую цену это добавляет к ядру;
- улучшает ли это analyzability;
- уменьшает ли change cost;
- не заменяет ли это явность магией.

---

## 12. Краткая модель системы в одной фразе

Framework `v0` — это explicit, PSR-first, synchronous HTTP kernel, который собирает runtime из config и routes, прогоняет request через middleware и router, разрешает handler через container и возвращает PSR-7 response, сохраняя ядро минимальным и реконструируемым.
