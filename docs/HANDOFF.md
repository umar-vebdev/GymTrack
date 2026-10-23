# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.
> Цель: новая сессия без истории чата продолжает ровно с этого места. Держать в пределах ~100 строк.

**Обновлено:** (дата и время)
**Этап:** 1 — Business (Аутентификация, пользователи и компании)
**Ветка:** main

## Текущая задача

- Задача: T1-18 Создание типа абонемента (UseCase `CreateMembershipType` + `POST /api/v1/business/membership-types`, только владелец)
- Статус: не начата
- **НАПОМИНАНИЕ (требование разработчика):** после реализации E1-F (Sales/платежи) дополнить блок Memberships (выдача вместе с `PaymentMark`, возвраты). Записано в `TASKS.md` в E1-E.

## Последняя сессия: что сделано

- **T1-17**: миграция `membership_types`, `MembershipType`, `MembershipTypeRepositoryInterface` + Eloquent-реализация, `MembershipsServiceProvider` (зарегистрирован в `bootstrap/providers.php`). Допущение D-003 (валюта TJS по умолчанию).
- T1-16: домен правил абонемента.

## Точный следующий шаг

1. T1-18: `Memberships/Application/CreateMembershipType` (company_id, name, price_minor, currency=TJS по умолчанию, поля правил → `MembershipRules::create`), `CreateMembershipTypeRequest`, `CreateMembershipTypeController`, `Presentation/routes.php` (`POST /api/v1/business/membership-types`, `auth:sanctum`).
2. Права: компания и роль через `Companies/Contracts/StaffCompanyResolverInterface` (`companyIdForUser`, `isOwner`); не владелец → 403 `FORBIDDEN`. `InvalidMembershipRules` → 422 `INVALID_MEMBERSHIP_RULES` (ловить в контроллере).
3. Feature-тест: успех, 403 для администратора, 422 для невалидных правил, 401.

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
