# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.
> Цель: новая сессия без истории чата продолжает ровно с этого места. Держать в пределах ~100 строк.

**Обновлено:** 2026-10-24
**Этап:** 1 — Business (E1-E Memberships)
**Ветка:** main

## Текущая задача

- Задача: T1-25b Заморозка/разморозка (HTTP API): эндпоинты `POST /api/v1/business/memberships/{id}/freeze` и `/unfreeze` + Feature-тесты
- Статус: в работе (`[~]`)
- **НАПОМИНАНИЕ (требование разработчика):** после реализации E1-F (Sales/платежи) дополнить блок Memberships (выдача вместе с `PaymentMark`, возвраты). Записано в `TASKS.md` в E1-E.

## Последняя сессия: что сделано

- **T1-25a** закрыта: добавлены `MembershipNotFound` и `MembershipFreezeNotAllowed`, обновлены `Membership::freeze()` / `unfreeze()`, созданы Use Cases `FreezeMembership` и `UnfreezeMembership` (транзакция + `findByIdForUpdate` + `Clock`).

## Точный следующий шаг

1. T1-25b: привязать `Clock::class` к `SystemClock::class` в `AppServiceProvider`, создать `FreezeMembershipController` и `UnfreezeMembershipController`, зарегистрировать маршруты `POST /api/v1/business/memberships/{id}/freeze` и `/unfreeze` (доступ владельцу и администратору компании, 404 `MEMBERSHIP_NOT_FOUND` для чужой компании, 409 `MEMBERSHIP_FREEZE_NOT_ALLOWED` при нарушении правил заморозки), покрыть Feature-тестами.
2. Запустить полный набор проверок, включая `docker compose exec -T app composer test`.

## Окружение: важное про Docker

- Из песочницы агента сокет Docker не виден: команды `docker compose ...` запускать с обходом песочницы (нужно подтверждение разработчика).

## Окружение и команды

| Действие | Команда |
|---|---|
| Запуск окружения | `docker compose up -d` |
| Стиль | `composer lint` |
| Статический анализ | `composer analyse` |
| Границы модулей | `composer deps` |
| Тесты | `docker compose exec -T app composer test` (на хосте нет pgsql) |

## Важные факты, которые легко забыть

- Платежей нет, только `PaymentMark`.
- Новая функция = новый модуль или расширение через Contracts и события.
