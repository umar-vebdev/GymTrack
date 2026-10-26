# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.
> Цель: новая сессия без истории чата продолжает ровно с этого места. Держать в пределах ~100 строк.

**Обновлено:** 2026-10-26
**Этап:** 1 — Business (E1-E Memberships)
**Ветка:** main

## Текущая задача

- Задача: T1-26b Отмена абонемента (HTTP API): `CancelMembershipRequest`, `CancelMembershipController`, `POST /api/v1/business/memberships/{id}/cancel` + Feature-тесты
- Статус: в работе (`[~]`)
- **НАПОМИНАНИЕ (требование разработчика):** после реализации E1-F (Sales/платежи) дополнить блок Memberships (выдача вместе с `PaymentMark`, возвраты). Записано в `TASKS.md` в E1-E.

## Последняя сессия: что сделано

- **T1-26a** закрыта: добавлены `MembershipAlreadyCancelled`, событие `MembershipCancelled` (`AuditableEvent`), сценарий `CancelMembership` (транзакция + `findByIdForUpdate` + отправка события после транзакции).

## Точный следующий шаг

1. T1-26b: создать `CancelMembershipRequest` (`reason`: required, string, max:500, trim), `CancelMembershipController` (проверка владельца через `StaffCompanyResolverInterface::isOwner`, 403 для администратора/постороннего, 404 `MEMBERSHIP_NOT_FOUND`, 409 `MEMBERSHIP_ALREADY_CANCELLED`), зарегистрировать маршрут `POST /api/v1/business/memberships/{id}/cancel`, написать `CancelMembershipTest`.

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
