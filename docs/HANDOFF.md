# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.
> Цель: новая сессия без истории чата продолжает ровно с этого места. Держать в пределах ~100 строк.

**Обновлено:** 2026-10-28
**Этап:** 1 — Business (E1-F Sales)
**Ветка:** main

## Текущая задача

- Задача: T1-34 История покупок клиента и список продаж зала (`GET /api/v1/business/clients/{id}/sales`, `GET /api/v1/business/sales`)
- Статус: не начата (`[ ]`, следующая к выполнению после коммита T1-33)
- **НАПОМИНАНИЕ (требование разработчика):** возврат продажи в `T1-35` отменяет выданный абонемент через контракт.

## Последняя сессия: что сделано

- **T1-33** закрыта: идемпотентность по заголовку `Idempotency-Key` (`UNIQUE (company_id, idempotency_key)` в `sales_sales`) и HTTP-эндпоинт кассы `POST /api/v1/business/sales`. Всего 151 тест зелёный.

## Точный следующий шаг

1. T1-34: создать `ListCompanySales`, `ListClientSales`, `ListCompanySalesController`, `ListClientSalesController`, зарегистрировать `GET /api/v1/business/sales` и `GET /api/v1/business/clients/{id}/sales` в `app/Modules/Sales/Presentation/routes.php`, написать `tests/Feature/Sales/ListSalesTest.php`.

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
