# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.
> Цель: новая сессия без истории чата продолжает ровно с этого места. Держать в пределах ~100 строк.

**Обновлено:** (дата и время)
**Этап:** 1 — Business (Аутентификация, пользователи и компании)
**Ветка:** main

## Текущая задача

- Задача: T1-19 Список типов и активация/деактивация
- Статус: не начата
- **НАПОМИНАНИЕ (требование разработчика):** после реализации E1-F (Sales/платежи) дополнить блок Memberships (выдача вместе с `PaymentMark`, возвраты). Записано в `TASKS.md` в E1-E.

## Последняя сессия: что сделано

- **T1-18**: `CreateMembershipType` + `CreateMembershipTypeCommand`, `POST /api/v1/business/membership-types` (только владелец; 422 для невалидных правил).
- T1-17: инфраструктура типов; T1-16: домен правил.

## Точный следующий шаг

1. T1-19: в `MembershipTypeRepositoryInterface` добавить `listByCompany(int $companyId, bool $onlyActive)` и `setActive(int $id, bool $active)`; UseCase `ListMembershipTypes` и `SetMembershipTypeActive` (проверять, что тип принадлежит компании, иначе `MembershipTypeNotFound` → 404).
2. `GET /api/v1/business/membership-types` (владелец и администратор, фильтр `active`), `PATCH /api/v1/business/membership-types/{id}/active` (только владелец, тело `{ "is_active": bool }`).
3. Feature-тест: список, деактивация владельцем, 403 администратору, изоляция компаний (чужой тип → 404).

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
