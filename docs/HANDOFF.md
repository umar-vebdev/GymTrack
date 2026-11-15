# Handoff — GymTrack

**Обновлено:** 2026-11-15
**Текущий этап:** Этап 7 — Переход OTP и связывания аккаунтов на Email (решение `D-020`)
**Текущая задача:** `T7-02` — Регистрация клиента, вход и HTTP-эндпоинты OTP по Email (`Identity`)

## Что сделано в последней сессии

- Принято архитектурное решение **`D-020`**: полный перевод OTP-подтверждения, регистрации клиента, автосвязывания CRM-карточек и добавления сотрудников на `email` (без платного SMS OTP; уведомления залов в `Notifications` сохранены).
- **`T7-01`** (готово): в `Identity` добавлены `emailVerifiedAt` и `markEmailAsVerified()` в доменную сущность `User` и `EloquentUserRepository`, нормализация/case-insensitive поиск по `email`, событие `EmailVerified`, контракт `EmailOtpGateway` и реализация `MailEmailOtpGateway` (на Laravel `Mail::raw`), `findVerifiedUserIdByEmail` в `UserQueryInterface`/`EloquentUserQuery`, use case'ы `SendEmailVerificationOtp` и `VerifyEmail`, тесты в `UserRepositoryTest`.

## Актуальное состояние проверок

- `composer lint` (`pint --test`) — ✓
- `composer analyse` (`phpstan`, level 8) — ✓ (0 errors)
- `composer deps` (`deptrac`) — ✓ (0 violations)
- `docker compose exec -T app php artisan test --filter=UserRepositoryTest` — ✓ (`6 passed, 39 assertions`)

## Точный следующий шаг

1. Выполнить **`T7-02`**: перевести `RegisterClient*`, `LoginUser*`/`IssueToken`, `SendOtp*`, `VerifyEmail*`, `GetClientProfileController`, rate limiters в `AppServiceProvider` и маршруты `/api/v1/auth/verify-email` на Email; удалить устаревшие SMS OTP классы из `Identity` (кроме `PhoneVerified.php` до завершения `T7-03`) и обновить тесты.
2. После этого выполнить `T7-03` (`Clients`) и `T7-04` (`Companies` + OpenAPI/README).
