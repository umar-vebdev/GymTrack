# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.
> Цель: новая сессия без истории чата продолжает ровно с этого места. Держать в пределах ~100 строк.

**Обновлено:** 2026-10-28
**Этап:** 1 — Business (E1-F Sales)
**Ветка:** main

## Текущая задача

- Задача: T1-30b Управление товарами (HTTP API + Feature-тесты): `POST /api/v1/business/products`, `GET /api/v1/business/products`, `PATCH /api/v1/business/products/{id}`
- Статус: в работе (`[~]`)
- **НАПОМИНАНИЕ (требование разработчика):** выдача абонемента в `T1-32` идёт в одной транзакции с `PaymentMark`, возврат продажи в `T1-35` отменяет выданный абонемент через контракт.

## Последняя сессия: что сделано

- **T1-30a** закрыта: добавлены `ProductNotFound`, `CreateProduct`, `ListProducts`, `UpdateProduct`.

## Точный следующий шаг

1. T1-30b: создать `CreateProductRequest`, `UpdateProductRequest`, `CreateProductController` (только владелец), `ListProductsController` (сотрудники), `UpdateProductController` (только владелец), `Presentation/routes.php`, написать `tests/Feature/Sales/ProductsManagementTest.php` и прогнать все проверки.

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
