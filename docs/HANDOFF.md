# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.

**Обновлено:** 2026-11-12
**Этап:** 4 — Расписание и тренеры (E4-A и E4-B завершены; E4-C в работе)
**Ветка:** main

## Текущее состояние

- Этапы 0–3 завершены и приняты.
- Эпик **E4-A (Тренеры) завершён** (`T4-01`…`T4-04`).
- Эпик **E4-B (Расписание) завершён** (`T4-05a`…`T4-08b`).
- Эпик **E4-C (Запись клиентов)** в работе (`D-013`): завершены `T4-09a`, `T4-09b`, `T4-10a`, следующая — `T4-10b`.

## Последняя сессия

- **D-013**: зафиксировано подтверждение плана E4-C и выбор `ClientLookupInterface::findOrCreateForUser`.
- **T4-09a [x]**: `ClassBookingStatus`, `InvalidClassBooking`, `ClassBooking` и 3 теста в `ClassBookingTest`.
- **T4-09b [x]**: миграция `schedule_bookings`, `ClassBookingRepositoryInterface`, `ClassBookingModel`, `EloquentClassBookingRepository`, привязка в `ScheduleServiceProvider` и 2 теста в `ClassBookingRepositoryTest`.
- **T4-10a [x]**: `BookClassSession` (`BookClassSessionCommand`, `ClassSessionRepositoryInterface::findCompanyId`, `ClassSession::assertOpenForBooking`) + 2 теста в `ClassBookingUseCasesTest`.
- **472 теста, 2740 проверок**; `composer lint`, `composer analyse`, `composer test`, `composer deps` проходят.

## Текущая задача и точный следующий шаг

1. Взять в работу **T4-10b**: сценарии `CancelClassBooking` (идемпотентная отмена с блокировкой строки занятия `findByIdForUpdate` и `releaseSeat()`), `ListSessionBookings` и `ListClientBookings` + тесты в `ClassBookingUseCasesTest`.

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
