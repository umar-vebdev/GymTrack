# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.
> Цель: новая сессия без истории чата продолжает ровно с этого места. Держать в пределах ~100 строк.

**Обновлено:** 2026-11-02
**Этап:** 2 — Client (эпик E2-E API для приложения клиента: мои абонементы, история)
**Ветка:** main

## Текущая задача

- **T2-21** (`[x]`): эндпоинт абонементов клиента (`GET /api/v1/client/memberships`) с фильтрами по статусу и компании.
- Следующая активная задача: **T2-22** (`[ ]`) — Эндпоинт истории посещений клиента (`GET /api/v1/client/visits`).

## Последняя сессия: что сделано

- **T2-20** (`1efd2f3`): DTO `ExpiringMembershipDto`, метод `findExpiringBetween` в `MembershipLookupInterface`, сценарий `SendExpiringMembershipReminders`, команда `notifications:send-expiring-reminders` и расписание.
- Завершён эпик **E2-D Notifications** (все задачи закрыты).
- **T2-21**:
  - Метод `findAllClientIdsByUser` в `ClientLookupInterface` и `EloquentClientLookup`.
  - DTO `ClientMembershipItemDto` в `Memberships/Contracts`.
  - Метод `listByClientIds` в `MembershipRepositoryInterface` и `EloquentMembershipRepository` с фильтрами `status=active|past` и `company_id`.
  - Связь `type(): BelongsTo` в `MembershipModel`.
  - Use Case `ListMyMemberships`, запрос `ListMyMembershipsRequest` и контроллер `ListMyMembershipsController`.
  - Маршрут `GET /api/v1/client/memberships` под `auth:sanctum`.
  - Feature-тесты `ListMyMembershipsTest` (6 проверок: неавторизованный доступ 401, пустой список для клиента без залов, сбор абонементов по разным залам, фильтрация active/past, фильтрация по компании, ошибка валидации 422).
- Всего 265 тестов (1136 проверок) зелёные, `lint`/`analyse`/`deps` чистые.

## Точный следующий шаг

1. Перечитать раздел Бизнес-ТЗ 5.2 и Технического ТЗ 7.1 для задачи **T2-22**.
2. Поставить статус `[~]` для **T2-22** в `docs/TASKS.md` и обновить `docs/HANDOFF.md`.
3. Создать DTO `ClientVisitItemDto` в `Visits/Contracts`.
4. Добавить метод `listForClients` с пагинацией и фильтрами в `VisitRepositoryInterface` и `EloquentVisitRepository`.
5. Создать Use Case `ListMyVisits`, `ListMyVisitsRequest`, контроллер `ListMyVisitsController` и маршрут `GET /api/v1/client/visits`.
6. Написать Feature-тесты `ListMyVisitsTest`.

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
