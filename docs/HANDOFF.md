# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.
> Цель: новая сессия без истории чата продолжает ровно с этого места. Держать в пределах ~100 строк.

**Обновлено:** 2026-10-26
**Этап:** 1 — Business (E1-E Memberships)
**Ветка:** main

## Текущая задача

- Задача: T1-28 Список абонементов, заканчивающихся в ближайшие 7 дней (Бизнес-ТЗ 4.4): `GET /memberships/expiring`
- Статус: не начата
- **НАПОМИНАНИЕ (требование разработчика):** после реализации E1-F (Sales/платежи) дополнить блок Memberships (выдача вместе с `PaymentMark`, возвраты). Записано в `TASKS.md` в E1-E.

## Последняя сессия: что сделано

- **T1-27a, T1-27b** закрыты: список абонементов клиента (`GET /api/v1/business/clients/{id}/memberships`), Use Case `ExpireDueMemberships` (с явной передачей `companyId` и идемпотентностью), консольная команда `memberships:expire-due` + ежедневный планировщик в `routes/console.php`. Всего 133 теста зелёные.

## Точный следующий шаг

1. T1-28: метод репозитория `listExpiringBetween(int $companyId, DateTimeImmutable $from, DateTimeImmutable $to): array` (активные абонементы с `ends_at` в интервале `[$from, $to]`, отсортированные по `ends_at` по возрастанию), Use Case `ListExpiringMemberships` (по умолчанию 7 дней от `Clock::now()`), контроллер `ListExpiringMembershipsController` и маршрут `GET /api/v1/business/memberships/expiring` (доступ сотрудникам зала), Feature-тесты.

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
