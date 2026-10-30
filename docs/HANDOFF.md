# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.
> Цель: новая сессия без истории чата продолжает ровно с этого места. Держать в пределах ~100 строк.

**Обновлено:** 2026-10-30
**Этап:** 1 — Business (эпик E1-H Analytics)
**Ветка:** main

## Текущая задача

- **T1-41** `[x]` Контракт аналитики продаж (`SalesAnalyticsQueryInterface`) и его реализация в модуле `Sales`.
- **T1-42** `[x]` Контракт аналитики клиентов (`ClientsAnalyticsQueryInterface`) и его реализация в модуле `Clients`.
- **T1-43** `[~]` Модуль `Analytics`: Use Cases `GetRevenueReport` и `GetOverviewAnalytics`, эндпоинты `GET /api/v1/business/analytics/revenue` и `GET /api/v1/business/analytics/overview`.

## Последняя сессия: что сделано

- **T1-41** закрыта: добавлены DTO `RevenueSummary` и `ItemPopularity`, интерфейс `SalesAnalyticsQueryInterface` в `Sales\Contracts` и реализация `EloquentSalesAnalyticsQuery`.
- **T1-42** закрыта: добавлены DTO `ClientsAnalyticsSummary`, интерфейс `ClientsAnalyticsQueryInterface` в `Clients\Contracts` и реализация `EloquentClientsAnalyticsQuery` + тест `ClientsAnalyticsQueryTest`.
- Всего 165 тестов (642 проверки) зелёные.

## Точный следующий шаг

1. Выполнить **T1-43** (модуль `Analytics`, Use Cases `GetRevenueReport` и `GetOverviewAnalytics`, эндпоинты `GET /api/v1/business/analytics/revenue` и `GET /api/v1/business/analytics/overview`, проверка роли владельца — критерий приёмки 12.7).

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
