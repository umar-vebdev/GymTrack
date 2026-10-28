# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.
> Цель: новая сессия без истории чата продолжает ровно с этого места. Держать в пределах ~100 строк.

**Обновлено:** 2026-10-28
**Этап:** 1 — Business (E1-F Sales)
**Ветка:** main

## Текущая задача

- Задача: T1-35a Контракт `MembershipCancellerInterface` и Use Case `RefundSale`
- Статус: не начата (`[ ]`, следующая к выполнению после коммита T1-34)
- **НАПОМИНАНИЕ (требование разработчика):** возврат продажи в `T1-35a`/`T1-35b` отменяет выданный абонемент через контракт.

## Последняя сессия: что сделано

- **T1-33** закрыта: идемпотентность по заголовку `Idempotency-Key` и эндпоинт кассы `POST /api/v1/business/sales`.
- **T1-34** закрыта: история покупок клиента (`GET /api/v1/business/clients/{id}/sales`) и список продаж зала (`GET /api/v1/business/sales`). Всего 153 теста зелёные.

## Точный следующий шаг

1. T1-35a: создать `MembershipCancellerInterface` в `app/Modules/Memberships/Contracts`, реализовать его в `CancelMembership` и зарегистрировать в `MembershipsServiceProvider`; создать `SaleNotFound`, `SaleRefunded` и `RefundSale`.
2. T1-35b: создать `RefundSaleRequest`, `RefundSaleController`, маршрут `POST /api/v1/business/sales/{id}/refund` и `tests/Feature/Sales/RefundSaleTest.php`.

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
