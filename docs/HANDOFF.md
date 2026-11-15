# Handoff — GymTrack

**Обновлено:** 2026-11-15
**Текущий этап:** Этап 8 — Подготовка API к мобильному приложению (`D-021`) — завершён
**Текущая задача:** Все задачи Этапов 0–8 завершены (`T8-01`–`T8-04` `[x]`)

## Что сделано в последней сессии

- Подготовлено и разделено мобильное ТЗ (`b71b0e8`, решение **`D-021`**) на два отдельных продукта: [uGym_Mobile_TZ.md](specs/uGym_Mobile_TZ.md) (для посетителей) и [uGym_Business_Mobile_TZ.md](specs/uGym_Business_Mobile_TZ.md) (для владельцев зала, администраторов и тренеров).
- **`T8-01`** (`ed5280f`): серверный выход `POST /api/v1/auth/logout`, сброс пароля по Email OTP `POST /api/v1/auth/reset-password`, обёртка `data.message` + `message` в `send-otp` и `verify-email`.
- **`T8-02`** (`4a6fdbe`): поддержка публичных ULID (`public_id`) наряду с числовыми ID в `POST /api/v1/client/membership-requests`, `GET /api/v1/client/gyms/{gym}/schedule` и `POST /api/v1/client/schedule/sessions/{session}/book`.
- **`T8-03`** (`ac16be4`): добавлена миграция `2026_11_15_000004_add_idempotency_key_to_workouts_tables` и поддержка заголовка `Idempotency-Key` (до 128 символов) в `POST /api/v1/client/workouts/sessions` и `POST /api/v1/client/workouts/sessions/{session}/sets`.
- **`T8-04`** (готово): актуализированы `docs/api/{identity-auth,business-core,schedule,workouts}.yaml`, мобильные ТЗ и `README.md`; провалидированы все 11 YAML-файлов и пройден полный набор проверок.

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

- Бэкенд Этапов 0–8 полностью завершён и проверен; следующий шаг — старт мобильной разработки по [uGym_Mobile_TZ.md](specs/uGym_Mobile_TZ.md) (клиентское приложение, этапы `M0–M4`) и [uGym_Business_Mobile_TZ.md](specs/uGym_Business_Mobile_TZ.md) (бизнес-приложение, этапы `B0–B4`) после выбора стека в `docs/DECISIONS.md`, либо выполнение `php artisan migrate` на целевом окружении для применения `2026_11_15_000004_add_idempotency_key_to_workouts_tables`.
