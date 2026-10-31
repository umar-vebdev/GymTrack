# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.
> Цель: новая сессия без истории чата продолжает ровно с этого места. Держать в пределах ~100 строк.

**Обновлено:** 2026-10-31
**Этап:** 2 — Client (завершены эпик E2-B Visits и задача T2-00 по валюте компании; переход к E2-C Заявки на абонемент)
**Ветка:** main

## Текущая задача

- **E2-C Заявки на абонемент (Sales) и их автоотмена** (`[~]`):
  - **T2-11** (`[x]`): модель данных и домен заявок (`sales_membership_requests`, `MembershipRequest`, `MembershipRequestStatus`, репозиторий и тесты).
  - **T2-12** (`[x]`): создание и просмотр заявок клиентом (`POST /client/membership-requests` с `Idempotency-Key`, `GET /client/membership-requests`).
  - **T2-13** (`[x]`): список заявок в кабинете зала (`GET /business/membership-requests`).
  - **T2-14** (`[~]`): отметка оплаты администратором (`POST /business/membership-requests/{id}/pay` с выдачей абонемента и `PaymentMark`).
  - **T2-15** (`[ ]`): автоотмена просроченных заявок через 24 ч (`sales:expire-requests`) и ручная отмена.
- Следующий эпик: **E2-D Notifications**.

## Последняя сессия: что сделано

- **T2-11** (`[x]`): миграция `sales_membership_requests`, домен `MembershipRequest` и `MembershipRequestStatus`, `EloquentMembershipRequestRepository` и тесты.
- **T2-12** (`[x]`): создание и просмотр заявок клиентом (`POST /api/v1/client/membership-requests`, `GET /api/v1/client/membership-requests`).
- **T2-13** (`[x]`): сценарий `ListCompanyMembershipRequests`, эндпоинт кабинета зала `GET /api/v1/business/membership-requests` с фильтрацией по статусу `?status=...`, изоляцией тенантов по `company_id` и проверкой прав сотрудника, Feature-тесты.
- Всего 223 теста (977 проверок) зелёные, `lint`/`analyse`/`deps` чистые.

## Точный следующий шаг

1. Реализовать задачу **T2-14**: сценарий `PayMembershipRequest`, эндпоинт `POST /api/v1/business/membership-requests/{id}/pay` (с блокировкой `lockForUpdate`, проведением продажи, отметкой `PaymentMark`, выпуском абонемента через `MembershipIssuerInterface`, событием `MembershipRequestPaid` и Feature-тестами).

## Окружение: важное про Docker и Git

- Из песочницы агента сокет Docker не виден и `.git` открыт только на чтение: команды `docker compose ...` и `git commit` запускать с `BypassSandbox: true`.

## Окружение и команды

| Действие | Команда |
|---|---|
| Запуск окружения | `docker compose up -d` |
| Стиль | `composer lint` |
| Статический анализ | `composer analyse` |
| Границы модулей | `composer deps` |
| Тесты | `docker compose exec -T app php artisan test --compact` |

## Важные факты, которые легко забыть

- Платежей нет, только `PaymentMark`.
- Новая функция = новый модуль или расширение через `Contracts` и события (без прямых JOIN между таблицами разных модулей).
- Валюта зала хранится в `companies.currency_id` (FK на `currencies`, по умолчанию TJS = 1).
