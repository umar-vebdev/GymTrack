# Handoff — GymTrack

**Обновлено:** 2026-11-12
**Текущий этап:** Этап 4 — Расписание и тренеры (`E4-C` — Запись клиентов)
**Текущая задача:** `T4-11b` (готова к старту; `T4-11a` завершена)

## Что сделано в последней сессии

- **D-013:** Согласовано переиспользование `ClientLookupInterface::findOrCreateForUser(int $userId, int $companyId): int` без добавления дублирующего метода; утверждён план `E4-C` (`T4-09a`…`T4-11b`).
- **T4-09a** (`82fccbc`): `ClassBooking`, `ClassBookingStatus`, `InvalidClassBooking` + `ClassBookingTest` (468 тестов).
- **T4-09b** (`b36017a`): Миграция `schedule_bookings`, `ClassBookingRepositoryInterface`, `ClassBookingModel`, `EloquentClassBookingRepository`, биндинг в `ScheduleServiceProvider` + `ClassBookingRepositoryTest` (470 тестов).
- **T4-10a** (`2491b13`): `BookClassSession`, `BookClassSessionCommand`, `ClassSessionRepositoryInterface::findCompanyId`, `ClassSession::assertOpenForBooking` / `reserveSeat` + `ClassBookingUseCasesTest` (472 теста).
- **T4-10b** (`c1db491`): `CancelClassBooking`, `ListSessionBookings`, `ListClientBookings`, доменные исключения + `ClassBookingUseCasesTest` (474 теста).
- **T4-11a:** `ClassBookingResource`, `ListClientBookingsRequest`, `BookClassSessionController`, `CancelClassBookingController`, `ListClientBookingsController`, клиентские маршруты в `app/Modules/Schedule/Presentation/routes.php` + 2 feature-теста в `tests/Feature/Schedule/ClientClassBookingsTest.php`. Проверки `lint`, `analyse`, `deps` и Docker `test` пройдены: `476 passed (2841 assertions)`.

## Точный следующий шаг

1. Выполнить **T4-11b**: добавить `ListSessionBookingsController` для `GET /api/v1/business/schedule/sessions/{session}/bookings` (доступ `owner`/`admin`/`trainer` своей компании через `StaffCompanyResolverInterface`), обновить `docs/api/schedule.yaml` (клиентские и бизнес-эндпоинты записей) и дополнить `tests/Feature/Schedule/ClientClassBookingsTest.php`.
2. После закрытия `T4-11b` отметить эпик `E4-C` завершённым и перейти к планированию/выполнению следующего эпика этапа 4 (`E4-D — Посещения по расписанию` и роль тренера при необходимости, согласно `TASKS.md`).

## Команды проверки

```bash
composer lint
composer analyse
composer deps
docker compose exec -T app composer test
```

## Известные проблемы и замечания

- В feature-тестах Laravel кэширует контроллер на экземпляре `Route` в пределах одного теста, поэтому тестовый `Clock` с изменением времени внутри одного теста должен быть мутабельным/читающим текущее время из свойства теста.
- Коммиты на локальной ветке `main` впереди `origin/main` (пуш не выполнялся, только по запросу).

## Нерешённые вопросы

- Нет.
