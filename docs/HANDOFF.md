# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.

**Обновлено:** 2026-11-11
**Этап:** 4 — Расписание и тренеры (E4-A и E4-B завершены; E4-C ожидает подтверждения плана)
**Ветка:** main

## Текущее состояние

- Этапы 0–3 завершены и приняты.
- Эпик **E4-A (Тренеры) завершён** (`T4-01`…`T4-04`).
- Эпик **E4-B (Расписание) завершён** (`T4-05a`…`T4-08b`).
- Эпик **E4-C (Запись клиентов)** раскрыт в задачи `T4-09a`…`T4-11b` в `docs/TASKS.md` и предложен разработчику на подтверждение (раздел 4.3 `AGENTS.md`).

## Последняя сессия

- **T4-07b [x]**: сценарии `GenerateClassSessions`, `CancelClassSession` и `ListClassSessions` + 3 теста в `ClassSessionUseCasesTest`.
- **T4-08a [x]**: HTTP-эндпоинты кабинета зала `POST /api/v1/business/schedule/sessions/generate`, `POST /api/v1/business/schedule/sessions/{session}/cancel`, `GET /api/v1/business/schedule/sessions` + 2 теста в `ManageClassSessionsTest`.
- **T4-08b [x]**: `GymLookupInterface::findCompanyId` + `EloquentGymLookup`, клиентский эндпоинт `GET /api/v1/client/gyms/{gym}/schedule`, OpenAPI `docs/api/schedule.yaml` + тест в `ManageClassSessionsTest`.
- **465 тестов, 2648 проверок**; `composer lint`, `composer analyse`, `composer test`, `composer deps` проходят.

## Текущая задача и точный следующий шаг

1. После подтверждения разработчиком списка задач эпика **E4-C** начать с **T4-09a**: доменная сущность `ClassBooking`, `ClassBookingStatus`, `InvalidClassBooking` и unit-тест `ClassBookingTest`.

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
