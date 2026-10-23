# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.
> Цель: новая сессия без истории чата продолжает ровно с этого места. Держать в пределах ~100 строк.

**Обновлено:** (дата и время)
**Этап:** 1 — Business (Аутентификация, пользователи и компании)
**Ветка:** main

## Текущая задача

- Задача: T1-20 Домен абонемента клиента: `Membership` + `MembershipStatus`
- Статус: не начата
- **НАПОМИНАНИЕ (требование разработчика):** после реализации E1-F (Sales/платежи) дополнить блок Memberships (выдача вместе с `PaymentMark`, возвраты). Записано в `TASKS.md` в E1-E.

## Последняя сессия: что сделано

- **T1-19**: `ListMembershipTypes`, `SetMembershipTypeActive`, `GET /api/v1/business/membership-types`, `PATCH /api/v1/business/membership-types/{id}/active`.
- T1-18: создание типа абонемента; T1-17: инфраструктура типов; T1-16: домен правил.

## Точный следующий шаг

1. T1-20: Создать сущность `Membership` (богатая доменная модель, чистый PHP) в `Memberships/Domain/Membership.php`.
   - `MembershipStatus` (enum: PENDING, ACTIVE, FROZEN, EXPIRED, CANCELLED).
   - Методы `consumeVisit(DateTimeImmutable $at)`, `freeze()`, `unfreeze()`, `expireIfDue(DateTimeImmutable $now)`.
   - Исключения `NoVisitsLeft`, `MembershipNotUsable`.
   - Unit-тесты на поведение сущности без БД.

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
