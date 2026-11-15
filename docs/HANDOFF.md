# Handoff — GymTrack

**Обновлено:** 2026-11-15
**Текущий этап:** Этап 8 — Подготовка API к мобильному приложению (`D-021`) — завершён
**Текущая задача:** Все задачи Этапов 0–8 завершены (`T8-01`–`T8-04` `[x]`)

## Что сделано в последней сессии

- Подготовлено мобильное ТЗ `docs/specs/GymTrack_Mobile_TZ.md` (`b71b0e8`) и принято решение **`D-021`**.
- **`T8-01`** (`ed5280f`): серверный выход `POST /api/v1/auth/logout`, сброс пароля по Email OTP `POST /api/v1/auth/reset-password`, обёртка `data.message` + `message` в `send-otp` и `verify-email`.
- **`T8-02`** (`4a6fdbe`): поддержка публичных ULID (`public_id`) наряду с числовыми ID в `POST /api/v1/client/membership-requests`, `GET /api/v1/client/gyms/{gym}/schedule` и `POST /api/v1/client/schedule/sessions/{session}/book`.
- **`T8-03`** (`ac16be4`): добавлена миграция `2026_11_15_000004_add_idempotency_key_to_workouts_tables` и поддержка заголовка `Idempotency-Key` (до 128 символов) в `POST /api/v1/client/workouts/sessions` и `POST /api/v1/client/workouts/sessions/{session}/sets`.
- **`T8-04`** (готово): актуализированы `docs/api/{identity-auth,business-core,schedule,workouts}.yaml`, `docs/specs/GymTrack_Mobile_TZ.md` и `README.md`; провалидированы все 11 YAML-файлов и пройден полный набор проверок.

## Актуальное состояние проверок

- `composer lint` (`pint --test`) — ✓
- `composer analyse` (`phpstan`, level 8) — ✓ (0 errors)
- `composer deps` (`deptrac`) — ✓ (0 violations)
- Проверка YAML (`docs/api/*.yaml`, 11 файлов через `Yaml::parseFile`) — ✓
- `docker compose exec -T app composer test` — ✓ (`571 passed, 3973 assertions`)

## На заметку для дальнейшего развития

- В `EloquentClientLookup::findOrCreateForUser` при записи/заявке клиента, у которого ещё нет привязанной карточки по подтверждённому `email`, но в БД зала уже есть оффлайн-карточка с тем же неподтверждённым `phone` (или `''` при отсутствии телефона), возможно срабатывание уникального ограничения `(company_id, phone)` в `clients` (стоит учитывать при развитии политики обработки оффлайн-дублей).
- Для реальной отправки писем в продакшене в `.env` задаётся `MAIL_MAILER=smtp` (локально по умолчанию `log`).

## Точный следующий шаг

- Бэкенд Этапов 0–8 полностью завершён и проверен; следующий шаг — старт мобильной разработки по [GymTrack_Mobile_TZ.md](specs/GymTrack_Mobile_TZ.md) (выбор стека перед `M0` в `docs/DECISIONS.md`) или выполнение `php artisan migrate` на целевом окружении для применения `2026_11_15_000004_add_idempotency_key_to_workouts_tables`.
