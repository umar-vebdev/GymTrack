# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.
> Цель: новая сессия без истории чата продолжает ровно с этого места. Держать в пределах ~100 строк.

**Обновлено:** 2026-10-28
**Этап:** 1 — Business (E1-F Sales)
**Ветка:** main

## Текущая задача

- Задача: T1-31 Домен и инфраструктура продаж и отметок оплаты (`Sale`, `SaleItem`, `PaymentMark`, `PaymentMethod`, таблицы `sales_sales` и `sales_sale_items`, `SaleRepositoryInterface` + `EloquentSaleRepository`)
- Статус: не начата
- **НАПОМИНАНИЕ (требование разработчика):** выдача абонемента в `T1-32` идёт в одной транзакции с `PaymentMark`, возврат продажи в `T1-35` отменяет выданный абонемент через контракт.

## Последняя сессия: что сделано

- **T1-30a, T1-30b** закрыты: управление товарами (`CreateProduct`, `ListProducts`, `UpdateProduct`, эндпоинты `POST/GET /api/v1/business/products`, `PATCH /api/v1/business/products/{id}`) + Feature-тесты. Всего 140 тестов зелёные.

## Точный следующий шаг

1. T1-31: перечисления `PaymentMethod` (`cash` | `card_terminal` | `transfer`), `SaleItemKind` (`product` | `membership_type`), `SaleStatus` (`completed` | `refunded`), Value Object `PaymentMark` (способ оплаты, сумма `Money`, `acceptedByUserId`, `paidAt`), сущности `SaleItem` и `Sale`, миграция `sales_sales` и `sales_sale_items` (без удаления финансовых записей — Тех. ТЗ 6.1), `SaleRepositoryInterface` + `EloquentSaleRepository` и тест репозитория.

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
