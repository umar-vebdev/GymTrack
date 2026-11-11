# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.

**Обновлено:** 2026-11-11
**Этап:** 4 — Расписание и тренеры (E4-B в работе)
**Ветка:** main

## Текущее состояние

- Этапы 0–3 завершены и приняты. Эпик **E4-A (Тренеры) завершён** (`T4-01`…`T4-04`).
- Эпик **E4-B (Расписание)** в работе: `T4-05a`…`T4-07b` завершены, задача `T4-08` разбита по правилу 4.1 `AGENTS.md` на `T4-08a` и `T4-08b`.

## Последняя сессия

- **T4-05a…T4-06c [x]**: домен, репозиторий, сценарии и HTTP-эндпоинты шаблонов групповых занятий (`POST`/`PATCH`/`GET /api/v1/business/schedule/templates`).
- **T4-07a [x]**: таблица `schedule_sessions`, `ClassSessionStatus`, `ClassSession`, `ClassSessionRepositoryInterface` + `EloquentClassSessionRepository`.
- **T4-07b [x]**: сценарии `GenerateClassSessions` (идемпотентная генерация из активных шаблонов на период до 62 дней в транзакции), `CancelClassSession` (блокировка строки `findByIdForUpdate` в транзакции) и `ListClassSessions` + 3 теста в `ClassSessionUseCasesTest`.
- **462 теста, 2576 проверок**; `composer lint`, `composer analyse`, `composer test`, `composer deps` проходят.

## Текущая задача и точный следующий шаг

1. В работе **T4-08a [~]**: HTTP-эндпоинты кабинета зала для занятий расписания (`POST /api/v1/business/schedule/sessions/generate`, `POST /api/v1/business/schedule/sessions/{session}/cancel`, `GET /api/v1/business/schedule/sessions` с доступом для `owner`/`admin`) + feature-тесты `ManageClassSessionsTest`.
2. После закрытия `T4-08a` перейти к **T4-08b**: клиентский эндпоинт `GET /api/v1/client/gyms/{gym}/schedule` и OpenAPI `docs/api/schedule.yaml` (завершение эпика E4-B).

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
