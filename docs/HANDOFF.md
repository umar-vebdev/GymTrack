# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.

**Обновлено:** 2026-11-11
**Этап:** 4 — Расписание и тренеры (E4-B в работе)
**Ветка:** main

## Текущее состояние

- Этапы 0–3 завершены и приняты. Эпик **E4-A (Тренеры) завершён** (`T4-01`…`T4-04`).
- Эпик **E4-B (Расписание)** в работе: `T4-05a`…`T4-08a` завершены, осталась завершающая задача эпика `T4-08b`.

## Последняя сессия

- **T4-05a…T4-06c [x]**: домен, репозиторий, сценарии и HTTP-эндпоинты шаблонов групповых занятий (`POST`/`PATCH`/`GET /api/v1/business/schedule/templates`).
- **T4-07a [x]**: таблица `schedule_sessions`, `ClassSessionStatus`, `ClassSession`, `ClassSessionRepositoryInterface` + `EloquentClassSessionRepository`.
- **T4-07b [x]**: сценарии `GenerateClassSessions`, `CancelClassSession` и `ListClassSessions` + 3 теста в `ClassSessionUseCasesTest`.
- **T4-08a [x]**: HTTP-эндпоинты кабинета зала `POST /api/v1/business/schedule/sessions/generate`, `POST /api/v1/business/schedule/sessions/{session}/cancel`, `GET /api/v1/business/schedule/sessions` (`ClassSessionResource`, `GenerateClassSessionsRequest`, `ListClassSessionsRequest`, контроллеры и маршруты, доступ только `owner`/`admin`) + 2 теста в `ManageClassSessionsTest`.
- **464 теста, 2634 проверки**; `composer lint`, `composer analyse`, `composer test`, `composer deps` проходят.

## Текущая задача и точный следующий шаг

1. В работе **T4-08b [~]**: просмотр расписания зала клиентом `GET /api/v1/client/gyms/{gym}/schedule` (`GymLookupInterface::findCompanyId(gymId)` + `ListClientGymScheduleRequest`/`ListClientGymScheduleController`) и OpenAPI-спецификация `docs/api/schedule.yaml` + feature-тесты.
2. После закрытия `T4-08b` эпик **E4-B** завершён; перед стартом **E4-C (Запись клиентов)** раскрыть эпик в задачи и согласовать план с разработчиком (раздел 4.3 `AGENTS.md`).

## Окружение: важное про Docker и Git

- Из песочницы сокет Docker недоступен, `.git` доступен только для чтения: Docker и запись git требуют `BypassSandbox: true`. `composer lint`, `composer analyse` и `composer deps` работают внутри песочницы.

## Команды

| Действие | Команда |
|---|---|
| Запуск окружения | `docker compose up -d` |
| Стиль / анализ / границы | `composer lint` / `composer analyse` / `composer deps` |
| Тесты | `docker compose exec -T app composer test -- --compact --colors=never` |

## Важные факты

- Платежей нет, только `PaymentMark`.
- У одного аккаунта (`users.id`) может быть несколько карточек клиента в разных залах.
- Каталог публичный (`/api/v1/public/...`), модерация через `/api/v1/platform/...`.
- Витрина — таблица `companies_gym_profiles` (1:1 с `gyms`), в модуле Companies.
