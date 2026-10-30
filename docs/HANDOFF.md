# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.
> Цель: новая сессия без истории чата продолжает ровно с этого места. Держать в пределах ~100 строк.

**Обновлено:** 2026-10-30
**Этап:** 2 — Client (завершён эпик E2-B Visits, переход к E2-C Заявки на абонемент)
**Ветка:** main

## Текущая задача

- **E2-B Visits** (`T2-04`..`T2-10b`) `[x]` — полностью закрыт.
- Следующий эпик этапа 2: **E2-C Заявки на абонемент (Sales) и их автоотмена** (`[ ]` — нужно перечитать разделы ТЗ, раскрыть эпик в мелкие задачи и показать разработчику для подтверждения по правилу 4.3 `AGENTS.md`).

## Последняя сессия: что сделано

- **T2-07** (`d26f01f`, `52d7471`): контракт `MembershipVisitConsumerInterface` (`ConsumeMembershipVisit`, `Membership::restoreVisit`) и полиморфная цепочка правил `CheckInRule` (`NotFrozenRule`, `MembershipIsAvailableRule`, `DailyLimitNotExceededRule`, `WithinAllowedTimeRule`) с чтением лимита и окна времени из `MembershipRules` (решение D-007).
- **T2-08** (`839b0b9`, `e006072`, `d0d93c3`): поиск карточки клиента по `user_id` + `company_id` в `ClientLookupInterface`, сценарий `CheckInByQr` и эндпоинт `POST /api/v1/client/visits/check-in` с `Idempotency-Key`, блокировкой абонемента и аудитом.
- **T2-09** (`5b7ce40`, `b62bcff`): ручная отметка администратором `POST /api/v1/business/clients/{id}/visits` (включая `visited_at` задним числом) и отмена ошибочной отметки в течение 24 ч `POST /api/v1/business/visits/{id}/cancel` с возвратом занятия и событием `VisitCancelled`.
- **T2-10** (`433e94f` + финальный коммит `T2-10b`): списки посещений `GET /api/v1/business/clients/{id}/visits`, `GET /api/v1/business/visits`, контракт `VisitsAnalyticsQueryInterface` (`VisitsAnalyticsSummary`) и подключение показателей посещаемости и «спящих» клиентов в `GET /api/v1/business/analytics/overview`.
- Всего 203 теста (851 проверка) зелёные, `lint`/`analyse`/`deps` чистые.

## Точный следующий шаг

1. Перечитать разделы Бизнес-ТЗ (4.5, 5.4 сценарий Б, 12.3, 12.5) и Технического ТЗ (3.6, 9.2) по заявкам на абонемент (`MembershipRequest`).
2. Детализировать эпик **E2-C Заявки на абонемент (Sales) и их автоотмена** в `docs/TASKS.md` и показать список задач разработчику на подтверждение (правило 4.3 `AGENTS.md`).

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
