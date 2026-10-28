# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.
> Цель: новая сессия без истории чата продолжает ровно с этого места. Держать в пределах ~100 строк.

**Обновлено:** 2026-10-28
**Этап:** 1 — Business (E1-G Billing)
**Ветка:** main

## Текущая задача

- Задача: T1-39b HTTP-эндпоинт активации подписки админом платформы (`POST /api/v1/platform/companies/{id}/subscription/activate`) и Feature-тесты активации и `billing:expire-due`
- Статус: в работе (`[~]`)

## Последняя сессия: что сделано

- **T1-36**…**T1-39a** закрыты: `PlatformPlan`, `Subscription`, `TrialPolicy`, автостарт пробного периода при регистрации компании, эндпоинты просмотра планов и статуса подписки, Use Cases `ActivateCompanySubscription` и `ExpireDueSubscriptions` + команда `billing:expire-due`. Всего 160 тестов зелёные.

## Точный следующий шаг

1. T1-39b: добавить флаг `is_platform_admin` (`boolean`, default `false`) для роли «Админ платформы» (Бизнес-ТЗ 2; Тех. ТЗ 7.1 `/api/v1/platform/...`), создать `ActivateSubscriptionRequest` и `ActivateCompanySubscriptionController`, зарегистрировать маршрут `POST /api/v1/platform/companies/{id}/subscription/activate`, написать `tests/Feature/Billing/ActivateAndExpireSubscriptionTest.php`.

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
