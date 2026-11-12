# Handoff — GymTrack

**Обновлено:** 2026-11-12
**Текущий этап:** Этап 4 — Расписание и тренеры (`E4-C` — Запись клиентов)
**Текущая задача:** `T4-11a` (готова к старту; `T4-10b` завершена)

## Что сделано в последней сессии

- **D-013:** Согласовано переиспользование `ClientLookupInterface::findOrCreateForUser(int $userId, int $companyId): int` при записи клиента на занятие без добавления дублирующего метода; утверждён план `E4-C` (`T4-09a`…`T4-11b`).
- **T4-09a** (`82fccbc`): Доменные сущности `ClassBooking`, `ClassBookingStatus` (`booked`, `cancelled`) и `InvalidClassBooking` + 3 unit-теста в `tests/Unit/Schedule/ClassBookingTest.php` (468 тестов).
- **T4-09b** (`b36017a`): Миграция `schedule_bookings` (включая `schedule_bookings_company_client_idempotency_uniq` и частичный индекс `(session_id, client_id) WHERE status = 'booked'`), `ClassBookingRepositoryInterface`, `ClassBookingModel`, `EloquentClassBookingRepository`, биндинг в `ScheduleServiceProvider` + 2 теста в `tests/Unit/Schedule/ClassBookingRepositoryTest.php` (470 тестов).
- **T4-10a** (`2491b13`): `BookClassSession` + `BookClassSessionCommand`, `ClassSessionRepositoryInterface::findCompanyId`, `ClassSession::assertOpenForBooking` / `reserveSeat(?DateTimeImmutable)` + 2 feature-теста в `tests/Feature/Schedule/ClassBookingUseCasesTest.php` (472 теста).
- **T4-10b:** `CancelClassBooking`, `ListSessionBookings`, `ListClientBookings`, `ScheduleResourceNotFound::booking()`, `InvalidClassBooking::cannotCancelStartedSession()` + 2 feature-теста в `tests/Feature/Schedule/ClassBookingUseCasesTest.php`. Проверки `lint`, `analyse`, `deps` и Docker `test` пройдены: `474 passed (2766 assertions)`.

## Точный следующий шаг

1. Перейти к задаче **T4-11a**: реализовать `ClassBookingResource`, `ListClientBookingsRequest`, контроллеры `BookClassSessionController`, `CancelClassBookingController`, `ListClientBookingsController`, маршруты в `app/Modules/Schedule/Presentation/routes.php` и feature-тесты `tests/Feature/Schedule/ClientClassBookingsTest.php`.
2. Далее выполнить **T4-11b**: `GET /api/v1/business/schedule/sessions/{session}/bookings` для `owner`/`admin`/`trainer` своей компании и обновление `docs/api/schedule.yaml`.

## Команды проверки

```bash
composer lint
composer analyse
composer deps
docker compose exec -T app composer test
```

## Известные проблемы и замечания

- В локальном окружении на ветке `main` коммиты впереди `origin/main` (пуш не выполнялся, только по запросу).
- В тестах с подменой времени через `bindFixedClock` инстансы use cases с зависимостью `Clock` в конструкторе нужно получать через `$this->app->make(...)` после вызова `bindFixedClock`.

## Нерешённые вопросы

- Нет.
