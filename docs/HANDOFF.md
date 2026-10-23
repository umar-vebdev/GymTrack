# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.
> Цель: новая сессия без истории чата продолжает ровно с этого места. Держать в пределах ~100 строк.

**Обновлено:** (дата и время)
**Этап:** 1 — Business (Аутентификация, пользователи и компании)
**Ветка:** main

## Текущая задача

- Задача: T1-22 Инфраструктура абонементов: миграция `memberships`, `EloquentMembershipRepository`
- Статус: не начата
- **НАПОМИНАНИЕ (требование разработчика):** после реализации E1-F (Sales/платежи) дополнить блок Memberships (выдача вместе с `PaymentMark`, возвраты). Записано в `TASKS.md` в E1-E.

## Последняя сессия: что сделано

- **T1-21**: реализован паттерн Strategy для списания абонементов: `ConsumptionStrategy`, `VisitsConsumptionStrategy`, `TimeBasedConsumptionStrategy`, `ConsumptionStrategyResolver`, метод `decrementRemainingVisits` в `Membership`. Unit-тесты.
- T1-20: сущность `Membership`, `MembershipStatus`, исключения, тесты жизненного цикла.

## Точный следующий шаг

1. T1-22:
   - Создать миграцию `memberships` (id, company_id, client_id, type_id, status, remaining_visits, starts_at, ends_at, freeze_days_used, freeze_times_used, frozen_at, soft deletes, timestamps; ограничение целостности CHECK `remaining_visits >= 0`).
   - Создать модель `MembershipModel` (`BelongsToCompany`, soft delete).
   - Создать `MembershipRepositoryInterface` (методы `findById(int $id): ?Membership`, `findByIdForUpdate(int $id): ?Membership`, `save(Membership $membership): void`, `create(...)`).
   - Реализовать `EloquentMembershipRepository` и зарегистрировать в `MembershipsServiceProvider`.
   - Написать интеграционный тест репозитория с проверкой сохранения и блокировки строки (`findByIdForUpdate`).

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
