# Framework

Минимальный HTTP-фреймворк на PHP, который разрабатывается как учебно-инженерная система, а не как декоративный набор компонентов.

## Зачем существует проект

У проекта две связанные цели:

- построить собственный архитектурно честный PHP framework core;
- использовать его разработку как среду для развития системного мышления, понимания HTTP, DI, routing, bootstrap-процессов и инженерных trade-offs.

Поэтому код и документация здесь должны объяснять не только "что работает", но и "почему это устроено именно так".

## Что уже реализовано

Текущий `v0` покрывает минимальный HTTP lifecycle:

1. загрузка `.env` и `config/app.php`;
2. сборка container;
3. загрузка маршрутов;
4. создание middleware pipeline;
5. dispatch запроса в route handler;
6. преобразование ошибок в HTTP response;
7. emission ответа в SAPI.

## Что сознательно не входит в v0

- ORM и работа с базой данных;
- шаблоны и view layer;
- CLI kernel;
- event system;
- queues и scheduler;
- session/auth/csrf;
- named routes и URL generation;
- autowiring by default;
- provider-heavy extensibility model.

Отсутствие этих пунктов сейчас считается не дефектом, а границей `v0`.

## Структура репозитория

- `src/Config/` — конфигурация и загрузка окружения
- `src/Container/` — минимальный explicit DI container
- `src/Routing/` — маршруты, матчинг и результаты матчинга
- `src/Http/` — HTTP runtime helpers, resolvers, dispatchers, emitter, error responses
- `src/Foundation/` — верхнеуровневая сборка runtime
- `app/` — прикладные handler'ы и пользовательский код
- `bootstrap/` — bootstrap runtime
- `config/` — конфигурация приложения
- `routes/` — регистрация маршрутов
- `public/` — front controller
- `tests/` — калибровка и regression safety net
- `docs/` — обзорная документация по архитектуре и чтению кода
- `artifacts/execution/` — протокол принятых решений и изменений

## Как читать проект

Если цель — понять систему полностью, идти лучше в таком порядке:

1. [`docs/framework-architecture.md`](/C:/OSPanel/home/framework.ru/docs/framework-architecture.md)
2. [`public/index.php`](/C:/OSPanel/home/framework.ru/public/index.php)
3. [`bootstrap/app.php`](/C:/OSPanel/home/framework.ru/bootstrap/app.php)
4. [`src/Foundation/ApplicationFactory.php`](/C:/OSPanel/home/framework.ru/src/Foundation/ApplicationFactory.php)
5. [`src/Foundation/Application.php`](/C:/OSPanel/home/framework.ru/src/Foundation/Application.php)
6. [`src/Http/RouteDispatcher.php`](/C:/OSPanel/home/framework.ru/src/Http/RouteDispatcher.php)
7. [`src/Routing/Router.php`](/C:/OSPanel/home/framework.ru/src/Routing/Router.php)
8. [`src/Container/Container.php`](/C:/OSPanel/home/framework.ru/src/Container/Container.php)
9. [`tests/Foundation/ApplicationFactoryTest.php`](/C:/OSPanel/home/framework.ru/tests/Foundation/ApplicationFactoryTest.php)

Такой порядок даёт путь от общей архитектуры к реальному execution flow.

## Валидация

Проект калибруется через:

- `composer qa`
- `composer test`

`composer qa` включает:

- lint
- `phpcs`
- `phpstan` level `10`
- `psalm`

## Следующий шаг для понимания

Если нужно понять не только структуру, но и причинность решений, открывай архитектурный документ и параллельно ходи по коду в порядке, указанном выше. В проекте это считается нормальным способом чтения системы, а не "дополнительной документацией".
