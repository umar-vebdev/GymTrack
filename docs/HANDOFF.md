# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.
> Цель: новая сессия без истории чата продолжает ровно с этого места. Держать в пределах ~100 строк.

**Обновлено:** 2026-10-28
**Этап:** 1 — Business (E1-G Billing)
**Ветка:** main

## Текущая задача

- Задача: T1-37 Домен и инфраструктура подписки компании (`Subscription`, `TrialPolicy`)
- Статус: в работе (`[~]`)

## Последняя сессия: что сделано

- **T1-36** закрыта: домен и инфраструктура тарифов платформы (`PlatformPlan`, `BillingPeriod`, `billing_plans`, `EloquentPlatformPlanRepository`, `BillingServiceProvider`). Всего 157 тестов зелёные.

## Точный следующий шаг

1. T1-37: создать `SubscriptionStatus`, `TrialPolicy`, `Subscription`, `SubscriptionRepositoryInterface`, миграцию `billing_subscriptions`, `SubscriptionModel`, `EloquentSubscriptionRepository`, привязать в `BillingServiceProvider`, написать `tests/Unit/Billing/SubscriptionRepositoryTest.php`.

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
