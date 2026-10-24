# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.
> Цель: новая сессия без истории чата продолжает ровно с этого места. Держать в пределах ~100 строк.

**Обновлено:** (дата и время)
**Этап:** 1 — Business (Аутентификация, пользователи и компании)
**Ветка:** main

## Текущая задача

- Задача: T1-24 Выбор абонемента для списания: контракт `MembershipLookupInterface::findUsableForClient()`
- Статус: не начата
- **НАПОМИНАНИЕ (требование разработчика):** после реализации E1-F (Sales/платежи) дополнить блок Memberships (выдача вместе с `PaymentMark`, возвраты). Записано в `TASKS.md` в E1-E.

## Последняя сессия: что сделано

- **T1-22** закрыта (персистентность абонементов), **T1-23** закрыта: `IssueMembership` (реализует `Memberships/Contracts/MembershipIssuerInterface`), `MembershipFactory`/`IssuedTerms`, `MembershipRules::endsAtFor`, событие `MembershipIssued` (AuditableEvent → запись в аудит), исключения `MembershipTypeInactive`, `ClientNotAvailable`, контракт `Clients/Contracts/ClientLookupInterface`. Всего 112 тестов зелёные.

## Точный следующий шаг

1. T1-24: `Memberships/Contracts/MembershipLookupInterface::findUsableForClient(int $companyId, int $clientId, DateTimeImmutable $at): ?MembershipSummary` (DTO в Contracts: id, typeId, status, remainingVisits, endsAt). Выбирать из usable (Active/Pending, не истёкшие по `endsAt`, для visits — остаток > 0) тот, что заканчивается раньше (ТЗ 4.3; без `endsAt` — в конце списка). Нужен метод репозитория `listUsableByClient(...)`. Реализация — запрос в Infrastructure; тесты на выбор «раньше заканчивается».

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
