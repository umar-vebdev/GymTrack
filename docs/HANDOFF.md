# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.
> Цель: новая сессия без истории чата продолжает ровно с этого места. Держать в пределах ~100 строк.

**Обновлено:** 2026-10-28
**Этап:** 1 — Business (E1-F Sales)
**Ветка:** main

## Текущая задача

- Задача: T1-31b Инфраструктура продаж: миграция `sales_sales` и `sales_sale_items`, `SaleModel`, `SaleItemModel`, `SaleRepositoryInterface`, `EloquentSaleRepository`
- Статус: в работе (`[~]`)
- **НАПОМИНАНИЕ (требование разработчика):** выдача абонемента в `T1-32` идёт в одной транзакции с `PaymentMark`, возврат продажи в `T1-35` отменяет выданный абонемент через контракт.

## Последняя сессия: что сделано

- **T1-31a** закрыта: доменные классы `PaymentMethod`, `SaleStatus`, `PaymentMark`, `SaleItem`, `Sale` и unit-тесты `SaleDomainTest`.

## Точный следующий шаг

1. T1-31b: создать `SaleRepositoryInterface`, миграцию `2026_10_04_000001_create_sales_tables.php` (`sales_sales`, `sales_sale_items`), `SaleModel`, `SaleItemModel`, `EloquentSaleRepository`, привязать в `SalesServiceProvider` и написать `tests/Unit/Sales/SaleRepositoryTest.php`.

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
