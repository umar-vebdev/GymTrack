# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.
> Цель: новая сессия без истории чата продолжает ровно с этого места. Держать в пределах ~100 строк.

**Обновлено:** 2026-11-02
**Этап:** 2 — Client (эпик E2-E API для приложения клиента: мои абонементы, история)
**Ветка:** main

## Текущая задача

- **T2-22** (`[x]`): эндпоинт истории посещений клиента (`GET /api/v1/client/visits`) с пагинацией и фильтрами по статусу и компании.
- Следующая активная задача: **T2-23** (`[ ]`) — Эндпоинт истории покупок клиента (`GET /api/v1/client/purchases`).

## Последняя сессия: что сделано

- **T2-21** (`e32c813`): эндпоинт `GET /api/v1/client/memberships` со статусом active/past, компанией и DTO `ClientMembershipItemDto`.
- **T2-22**:
  - DTO `ClientVisitItemDto` в `Visits/Contracts`.
  - Метод `listForClients` в `VisitRepositoryInterface` и `EloquentVisitRepository` с пагинацией (`page`, `per_page`), фильтрами по `company_id` и `status` (`completed`, `cancelled`), сортировкой по `visited_at` desc.
  - Реализация `listForClients` в тестовом фейке `FakeVisitRepository`.
  - Use Case `ListMyVisits` через `ClientLookupInterface::findAllClientIdsByUser`.
  - Запрос `ListMyVisitsRequest` и контроллер `ListMyVisitsController` с метаданными пагинации `meta: {page, per_page, total}`.
  - Маршрут `GET /api/v1/client/visits` под `auth:sanctum`.
  - Feature-тесты `ListMyVisitsTest` (7 проверок: неавторизованный доступ 401, пустой список 200 с meta, посещения в разных залах, фильтрация по компании, фильтрация по статусу, пагинация page/per_page, ошибки валидации 422).
- Всего 272 теста (1170 проверок) зелёные, `lint`/`analyse`/`deps` чистые.

## Точный следующий шаг

1. Перечитать раздел Бизнес-ТЗ 5.2 и Тех. ТЗ 7.1 для задачи **T2-23**.
2. Поставить статус `[~]` для **T2-23** в `docs/TASKS.md` и обновить `docs/HANDOFF.md`.
3. Добавить метод `listForClients(array $clientIds, ?int $companyId, ?string $status, int $page, int $perPage)` в `SaleRepositoryInterface` и реализовать в `EloquentSaleRepository`.
4. Создать Use Case `ListMyPurchases` в `Sales/Application`, `ListMyPurchasesRequest`, контроллер `ListMyPurchasesController` и зарегистрировать маршрут `GET /api/v1/client/purchases`.
5. Написать Feature-тесты `ListMyPurchasesTest`.
6. Выполнить проверки: `composer lint && composer analyse && composer deps` и `docker compose exec -T app php artisan test --compact`.
7. Завершить задачу, закрыть эпик E2-E и этап 2.

## Окружение: важное про Docker и Git

- Из песочницы агента сокет Docker не виден и `.git` открыт только на чтение: команды `docker compose ...` и `git commit` запускать с `BypassSandbox: true`.

## Окружение и команды

| Действие | Команда |
|---|---|
| Запуск окружения | `docker compose up -d` |
| Стиль | `composer lint` |
| Статический анализ | `composer analyse` |
| Границы модулей | `composer deps` |
| Тесты | `docker compose exec -T app php artisan test --compact` |

## Важные факты, которые легко забыть

- Платежей нет, только `PaymentMark`.
- У одного аккаунта клиента (`users.id`) может быть несколько карточек (`clients.id`) в разных залах (мультиарендность).
- Клиентские эндпоинты агрегируют данные по всем залам клиента через `ClientLookupInterface`.
