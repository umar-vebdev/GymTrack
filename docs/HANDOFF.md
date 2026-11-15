# Handoff — GymTrack

**Обновлено:** 2026-11-15
**Текущий этап:** Этап 8 — Подготовка API к мобильному приложению (`D-021`)
**Текущая задача:** `T8-03` — Поддержка `Idempotency-Key` в `POST /api/v1/client/workouts/sessions` и `POST /api/v1/client/workouts/sessions/{session}/sets` (`[~]` в работе; `T8-01` и `T8-02` завершены)

## Что сделано в последней сессии

- Подготовлено мобильное ТЗ `docs/specs/GymTrack_Mobile_TZ.md` (`b71b0e8`) и принято решение **`D-021`**.
- **`T8-01`** (`ed5280f`): серверный выход `POST /api/v1/auth/logout`, сброс пароля по Email OTP `POST /api/v1/auth/reset-password`, обёртка `data.message` + `message` в `send-otp` и `verify-email`.
- **`T8-02`** (готово): поддержка публичных ULID (`public_id`) наряду с числовыми ID в `POST /api/v1/client/membership-requests`, `GET /api/v1/client/gyms/{gym}/schedule` и `POST /api/v1/client/schedule/sessions/{session}/book` через `GymLookupInterface::resolveId`, `GymQrLookupInterface::findByIdOrPublicId`, `MembershipIssuerInterface::resolveTypeId` и `ClassSessionRepositoryInterface::resolveId`; добавлены feature-тесты в `Sales` и `Schedule` (`199 passed, 1689 assertions` по сьютам `Sales`/`Schedule`/`Companies`/`Memberships`/`Catalog`).

## Актуальное состояние проверок

- `composer lint` (`pint --test`) — ✓
- `composer analyse` (`phpstan`, level 8) — ✓ (0 errors)
- `composer deps` (`deptrac`) — ✓ (0 violations)
- Проверка YAML (`docs/api/*.yaml`, 11 файлов через `Yaml::parseFile`) — ✓
- `docker compose exec -T app composer test` — ✓ (`564 passed, 3903 assertions`)

## На заметку для дальнейшего развития

- В `EloquentClientLookup::findOrCreateForUser` при записи/заявке клиента, у которого ещё нет привязанной карточки по подтверждённому `email`, но в БД зала уже есть оффлайн-карточка с тем же неподтверждённым `phone` (или `''` при отсутствии телефона), возможно срабатывание уникального ограничения `(company_id, phone)` в `clients` (стоит учитывать при развитии политики обработки оффлайн-дублей).
- Для реальной отправки писем в продакшене в `.env` задаётся `MAIL_MAILER=smtp` (локально по умолчанию `log`).

## Точный следующий шаг

- Выполнить **`T8-03`** — добавить миграцию и поддержку заголовка `Idempotency-Key` (до 128 символов) в `POST /api/v1/client/workouts/sessions` и `POST /api/v1/client/workouts/sessions/{session}/sets`, затем **`T8-04`** (синхронизация OpenAPI YAML и `GymTrack_Mobile_TZ.md` + `README.md` + полный прогон `composer test`).
