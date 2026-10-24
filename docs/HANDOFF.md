# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.
> Цель: новая сессия без истории чата продолжает ровно с этого места. Держать в пределах ~100 строк.

**Обновлено:** 2026-10-24
**Этап:** 1 — Business (E1-E Memberships)
**Ветка:** main

## Текущая задача

- Задача: T1-26 Отмена и возврат: UseCase `CancelMembership` (только владелец, обязательная причина, событие `MembershipCancelled` → Audit), `POST /memberships/{id}/cancel`
- Статус: не начата
- **НАПОМИНАНИЕ (требование разработчика):** после реализации E1-F (Sales/платежи) дополнить блок Memberships (выдача вместе с `PaymentMark`, возвраты). Записано в `TASKS.md` в E1-E.

## Последняя сессия: что сделано

- **T1-25a, T1-25b** закрыты: доменные исключения `MembershipNotFound` и `MembershipFreezeNotAllowed`, Use Cases `FreezeMembership` и `UnfreezeMembership` (транзакция + `findByIdForUpdate` + `Clock`), привязка `Clock` → `SystemClock`, эндпоинты `POST /api/v1/business/memberships/{id}/freeze` и `/unfreeze` + Feature-тесты. Всего 123 теста зелёные.

## Точный следующий шаг

1. T1-26: доменное исключение `MembershipCancelNotAllowed` (при повторной отмене в `Membership::cancel()`), событие `MembershipCancelled` (`AuditableEvent` с `company_id`, `user_id`, `action = MEMBERSHIP_CANCELLED`, `reason` в payload), Use Case `CancelMembership` (`findByIdForUpdate` в `DB::transaction`, проверка `companyId`, `cancel($reason)`, `save`, отправка события после транзакции).
2. Эндпоинт `POST /api/v1/business/memberships/{id}/cancel` + `CancelMembershipRequest` (`reason`: required, string): доступ **только владельцу** компании (администратору → 403), чужая компания → 404 `MEMBERSHIP_NOT_FOUND`, уже отменён → 409 `MEMBERSHIP_ALREADY_CANCELLED`.
3. Feature-тесты: отмена владельцем + запись в `audit_entries`, 403 для администратора, 422 без причины, 409 при повторной отмене, 404 для чужой компании.

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
