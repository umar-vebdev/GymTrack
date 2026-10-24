# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.
> Цель: новая сессия без истории чата продолжает ровно с этого места. Держать в пределах ~100 строк.

**Обновлено:** (дата и время)
**Этап:** 1 — Business (Аутентификация, пользователи и компании)
**Ветка:** main

## Текущая задача

- Задача: T1-22 Инфраструктура абонементов — `[~]`, код готов, **тесты не запускались** (Docker не был запущен)
- **НАПОМИНАНИЕ (требование разработчика):** после реализации E1-F (Sales/платежи) дополнить блок Memberships (выдача вместе с `PaymentMark`, возвраты). Записано в `TASKS.md` в E1-E.

## Последняя сессия: что сделано

- Убраны лишние same-namespace `use` в `Membership.php` (Pint падал после ручного коммита `ad0688a`).
- T1-22 (WIP): миграция `memberships` (CHECK `remaining_visits >= 0`, soft delete), `MembershipModel`, `MembershipRepositoryInterface` (`findById`, `findByIdForUpdate`, `create`, `save`), `EloquentMembershipRepository`, регистрация в `MembershipsServiceProvider`, тест `tests/Unit/Memberships/MembershipRepositoryTest.php` (4 теста, не запускались).

## Точный следующий шаг

1. Запустить Docker: `docker compose up -d`; затем `docker compose exec -T app composer test`.
2. Если тесты T1-22 зелёные — поставить `[x]`, закоммитить `T1-22: ...`. Если красные — исправить (вероятные места: приведение дат в `save`, CHECK-ограничение).
3. Дальше T1-23: `IssueMembership` + `MembershipIssuerInterface` + событие `MembershipIssued`.
4. Заметка для T1-26: `Membership::cancel(string $reason)` уже требует причину и хранит `cancellationReason`.

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
