# Handoff — GymTrack

**Обновлено:** 2026-11-15
**Текущий этап:** Этап 7 — Переход OTP и связывания аккаунтов на Email (решение `D-020`)
**Текущая задача:** `T7-04` — Добавление сотрудников по подтверждённому `email` и документация (`Companies` + Docs)

## Что сделано в последней сессии

- **`T7-01`** (`cea8d16`): в `Identity` добавлены `emailVerifiedAt`/`markEmailAsVerified`, событие `EmailVerified`, `EmailOtpGateway`/`MailEmailOtpGateway`, `findVerifiedUserIdByEmail`, `SendEmailVerificationOtp`, `VerifyEmail`, тесты `UserRepositoryTest`.
- **`T7-02`** (`555cd58`): `POST /api/v1/client/auth/register`, `POST /api/v1/auth/login`, `POST /api/v1/auth/send-otp`, `POST /api/v1/auth/verify-email`, `GetClientProfileController` и rate limiters в `AppServiceProvider` переведены на Email; устаревшие SMS OTP классы из `Identity` удалены.
- **`T7-03`** (готово): в `Clients` добавлена миграция `clients.email` (nullable, индекс `(company_id, email)`), обновлены `ClientModel`, `Client`, `EloquentClientRepository` (`linkUserByEmail`, поиск по email), `CreateClient*`, `SearchClientsController`, `LinkUserToClients`, `ClientsServiceProvider` (`EmailVerified`) и `EloquentClientLookup::findOrCreateForUser`; удалён `PhoneVerified.php`; обновлены тесты (`32 passed, 163 assertions`).

## Актуальное состояние проверок

- `composer lint` (`pint --test`) — ✓
- `composer analyse` (`phpstan`, level 8) — ✓ (0 errors)
- `composer deps` (`deptrac`) — ✓ (0 violations)
- `docker compose exec -T app php artisan test --filter="ClientUserLinkingTest|RegisterClientAndLinkingTest|Clients"` — ✓ (`32 passed, 163 assertions`)

## Точный следующий шаг

1. Выполнить **`T7-04`**: перевести `AddStaffMemberRequest`, `AddStaffMember`, `StaffController`, `StaffUseCasesTest` и `ManageStaffEndpointTest` на добавление сотрудников по подтверждённому `email`; удалить `findVerifiedUserIdByPhone` из `UserQueryInterface`/`EloquentUserQuery`; обновить `docs/api/{identity-auth,business-core,companies}.yaml` и `README.md`; прогнать `lint`, `analyse`, `deps`, проверку YAML и полный `composer test`.
