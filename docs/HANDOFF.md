# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.

**Обновлено:** 2026-11-12
**Этап:** 4 — Расписание и тренеры (E4-A и E4-B завершены; E4-C в работе)
**Ветка:** main

## Текущее состояние

- Этапы 0–3 завершены и приняты.
- Эпик **E4-A (Тренеры) завершён** (`T4-01`…`T4-04`).
- Эпик **E4-B (Расписание) завершён** (`T4-05a`…`T4-08b`).
- Эпик **E4-C (Запись клиентов)** в работе (план подтверждён 2026-11-12, `D-013`): завершена `T4-09a`, следующая — `T4-09b`.

## Последняя сессия

- **D-013**: зафиксировано подтверждение плана E4-C и выбор `ClientLookupInterface::findOrCreateForUser` при записи клиента на занятие.
- **T4-09a [x]**: `ClassBookingStatus`, `InvalidClassBooking`, доменная сущность `ClassBooking` и 3 теста в `tests/Unit/Schedule/ClassBookingTest.php`.
- **468 тестов, 2682 проверок**; `composer lint`, `composer analyse`, `composer test`, `composer deps` проходят.

## Текущая задача и точный следующий шаг

1. Взять в работу **T4-09b**: миграция `schedule_bookings`, `ClassBookingRepositoryInterface`, `ClassBookingModel`, `EloquentClassBookingRepository` и тест `ClassBookingRepositoryTest`.

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
