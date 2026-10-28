# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.
> Цель: новая сессия без истории чата продолжает ровно с этого места. Держать в пределах ~100 строк.

**Обновлено:** 2026-10-28
**Этап:** 1 — Business (E1-G Billing)
**Ветка:** main

## Текущая задача

- Задача: T1-38b HTTP-эндпоинты тарифов и статуса подписки зала (`GET /api/v1/business/billing/plans`, `GET /api/v1/business/billing/subscription`) и Feature-тесты
- Статус: в работе (`[~]`)

## Последняя сессия: что сделано

- **T1-36**, **T1-37**, **T1-38a** закрыты: `PlatformPlan`, `Subscription`, `TrialPolicy`, событие `CompanyRegistered` и слушатель `StartTrialOnCompanyRegistered`, Use Cases `ListPlatformPlans` и `GetCompanySubscription`. Всего 158 тестов зелёные.

## Точный следующий шаг

1. T1-38b: создать `ListPlatformPlansController`, `GetCompanySubscriptionController`, `app/Modules/Billing/Presentation/routes.php`, написать `tests/Feature/Billing/TrialAndSubscriptionStatusTest.php`.

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
