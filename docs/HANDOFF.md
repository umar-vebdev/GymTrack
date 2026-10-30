# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.
> Цель: новая сессия без истории чата продолжает ровно с этого места. Держать в пределах ~100 строк.

**Обновлено:** 2026-10-30
**Этап:** 2 — Client (переход к E2-A после полного завершения Этапа 1 Business)
**Ветка:** main

## Текущая задача

- **Этап 1 (Business)** полностью завершён (`E1-A`…`E1-H`, включая `T1-41`…`T1-43`).
- Следующий эпик: **E2-A Регистрация клиента и связывание с карточками зала** (`T2-01`…`T2-03`, ожидает подтверждения списка задач разработчиком).

## Последняя сессия: что сделано

- **T1-41** закрыта: добавлены DTO `RevenueSummary` и `ItemPopularity`, интерфейс `SalesAnalyticsQueryInterface` в `Sales\Contracts` и реализация `EloquentSalesAnalyticsQuery` + тест `SalesAnalyticsQueryTest`.
- **T1-42** закрыта: добавлены DTO `ClientsAnalyticsSummary`, интерфейс `ClientsAnalyticsQueryInterface` в `Clients\Contracts` и реализация `EloquentClientsAnalyticsQuery` + тест `ClientsAnalyticsQueryTest`.
- **T1-43** закрыта: реализован модуль `Analytics` (`GetRevenueReport`, `GetOverviewAnalytics`, `OverviewAnalyticsResult`, `RevenueReportController`, `OverviewAnalyticsController`, `AnalyticsPeriodRequest`, маршруты `GET /api/v1/business/analytics/revenue` и `GET /api/v1/business/analytics/overview`, доступ строго для владельца — критерий 12.7) + `AnalyticsEndpointsTest`.
- Всего 168 тестов (671 проверка) зелёные.

## Точный следующий шаг

1. Получить подтверждение разработчика по декомпозиции эпика **E2-A** (`T2-01`…`T2-03` в `docs/TASKS.md`).
2. Начать с **T2-01** (поля `birth_date` и `personal_data_consented_at` в `Identity`, проверка возраста 18+ и согласия).

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
