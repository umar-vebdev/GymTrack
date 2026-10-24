# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.
> Цель: новая сессия без истории чата продолжает ровно с этого места. Держать в пределах ~100 строк.

**Обновлено:** (дата и время)
**Этап:** 1 — Business (Аутентификация, пользователи и компании)
**Ветка:** main

## Текущая задача

- Задача: T1-23 Выдача абонемента: UseCase `IssueMembership`, контракт `MembershipIssuerInterface`, событие `MembershipIssued`
- Статус: не начата
- **НАПОМИНАНИЕ (требование разработчика):** после реализации E1-F (Sales/платежи) дополнить блок Memberships (выдача вместе с `PaymentMark`, возвраты). Записано в `TASKS.md` в E1-E.

## Последняя сессия: что сделано

- **T1-22** закрыта: миграция `memberships`, `MembershipModel`, `MembershipRepositoryInterface` (`findById`, `findByIdForUpdate`, `create`, `save`), `EloquentMembershipRepository`; 4 теста зелёные, всего 102.
- Убраны лишние same-namespace `use` в `Membership.php`.

## Точный следующий шаг

1. T1-23: `Memberships/Contracts/MembershipIssuerInterface::issue(companyId, clientId, typeId, DateTimeImmutable $at): int`; `Application/IssueMembership` (загрузить тип, проверить принадлежность компании и `isActive`, посчитать `status/starts_at/ends_at/remaining_visits`: `on_sale` → Active, `starts_at = $at`, `ends_at = $at + durationDays|validityDays` (single → конец дня); `first_visit` → Pending без дат; visits → `remaining_visits = visitsCount`), создать через `MembershipRepositoryInterface::create`, диспатчить `MembershipIssued` (реализует `AuditableEvent`, как в Audit). Проверку клиента (из той же компании) делать через контракт Clients — решить, нужен ли новый `Clients/Contracts/ClientLookupInterface` (вероятно да, ТЗ 3.5).
2. Эндпоинта нет (ручная выдача — в E1-F). Тесты: unit/Feature на UseCase для каждого вида абонемента и режима старта.

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
