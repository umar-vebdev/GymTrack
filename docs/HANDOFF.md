# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.
> Цель: новая сессия без истории чата продолжает ровно с этого места. Держать в пределах ~100 строк.

**Обновлено:** 2026-10-28
**Этап:** 1 — Business (E1-G Billing)
**Ветка:** main

## Текущая задача

- Задача: T1-38 Автоматический старт пробного периода при регистрации компании и просмотр статуса подписки
- Статус: в работе (`[~]`)

## Последняя сессия: что сделано

- **T1-36** и **T1-37** закрыты: домен и инфраструктура тарифов платформы (`PlatformPlan`, `billing_plans`) и подписки компании (`Subscription`, `TrialPolicy`, `billing_subscriptions`). Всего 158 тестов зелёные.

## Точный следующий шаг

1. T1-38: создать `CompanyRegistered` в `Companies\Contracts\Events` и отправить из `RegisterCompany`, создать слушатель `StartTrialOnCompanyRegistered`, Use Cases `ListPlatformPlans` и `GetCompanySubscription`, контроллеры и маршруты `GET /api/v1/business/billing/plans`, `GET /api/v1/business/billing/subscription`, написать `tests/Feature/Billing/TrialAndSubscriptionStatusTest.php`.

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
