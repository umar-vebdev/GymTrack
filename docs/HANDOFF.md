# Handoff — GymTrack

**Обновлено:** 2026-11-15
**Текущий этап:** Этап 7 — Переход OTP и связывания аккаунтов на Email (решение `D-020`)
**Текущая задача:** `T7-03` — Поле `email` в CRM и автосвязывание карточек клиента по `EmailVerified` (`Clients`)

## Что сделано в последней сессии

- **`T7-01`** (`cea8d16`): в `Identity` добавлены `emailVerifiedAt`/`markEmailAsVerified`, событие `EmailVerified`, `EmailOtpGateway`/`MailEmailOtpGateway`, `findVerifiedUserIdByEmail`, `SendEmailVerificationOtp`, `VerifyEmail`, тесты `UserRepositoryTest`.
- **`T7-02`** (готово): `POST /api/v1/client/auth/register` переведён на обязательный уникальный `email` (опциональный `phone`) и отправку OTP по Email; `POST /api/v1/auth/login` принимает `email` или `phone`; `POST /api/v1/auth/send-otp` и `POST /api/v1/auth/verify-email` работают по Email; обновлены `GetClientProfileController` (`is_email_verified`) и rate limiters в `AppServiceProvider`; удалены устаревшие SMS OTP классы из `Identity` (кроме `PhoneVerified.php` до `T7-03`); тесты `19 passed (109 assertions)`.

## Актуальное состояние проверок

- `composer lint` (`pint --test`) — ✓
- `composer analyse` (`phpstan`, level 8) — ✓ (0 errors)
- `composer deps` (`deptrac`) — ✓ (0 violations)
- `docker compose exec -T app php artisan test --filter="PhoneVerificationTest|ClientProfileAndDeletionTest|AuthRateLimitingTest|RegisterUserTest|LoginUserTest|HttpGatewaysTest"` — ✓ (`19 passed, 109 assertions`)

## Точный следующий шаг

1. Выполнить **`T7-03`**: миграция `clients.email` + обновление `ClientModel`/`Client`/`ClientRepositoryInterface`/`EloquentClientRepository`, `CreateClient*`, `LinkUserToClients`, `ClientsServiceProvider`, `EloquentClientLookup`, удаление `PhoneVerified.php`, обновление `ClientUserLinkingTest` и `RegisterClientAndLinkingTest`.
2. Выполнить `T7-04` (`Companies` + OpenAPI/README + полный прогон `composer test`).
