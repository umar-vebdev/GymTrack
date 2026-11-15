# Handoff — GymTrack

**Обновлено:** 2026-11-15
**Текущий этап:** Этап 7 завершён (`E7-A`, решение `D-020`)
**Текущая задача:** Нет активных задач (Этапы 0–7 полностью выполнены)

## Что сделано в последней сессии

- Принято архитектурное решение **`D-020`**: перевод OTP-подтверждения, регистрации клиента, автосвязывания CRM-карточек и добавления сотрудников на Email (уведомления залов в `Notifications` сохранены без изменений).
- **`T7-01`** (`cea8d16`): `emailVerifiedAt`/`markEmailAsVerified` и сброс в `anonymize()`, case-insensitive `findByEmail`, событие `EmailVerified`, шлюз `EmailOtpGateway` (`MailEmailOtpGateway`), `findVerifiedUserIdByEmail`, сценарии `SendEmailVerificationOtp` и `VerifyEmail`, тесты `UserRepositoryTest`.
- **`T7-02`** (`555cd58`): клиентская регистрация переведена на обязательный уникальный `email` и отправку Email OTP, вход поддерживает `email` или `phone`, эндпоинт `/api/v1/auth/verify-email`, обновлены `GetClientProfileController` (`is_email_verified`) и rate limiters в `AppServiceProvider`, удалены устаревшие SMS OTP классы из `Identity`.
- **`T7-03`** (`fec9061`): добавлена миграция `clients.email` (`nullable`, индекс `(company_id, email)`), обновлены `ClientModel`/`Client`/`EloquentClientRepository` (`linkUserByEmail`, поиск по email), `CreateClient*`, `SearchClientsController`, `LinkUserToClients`, `ClientsServiceProvider` (`EmailVerified`) и `EloquentClientLookup::findOrCreateForUser`; удалён `PhoneVerified`.
- **`T7-04`** (готово): `AddStaffMemberRequest`/`AddStaffMember`/`StaffController` переведены на добавление сотрудников по подтверждённому `email`, удалена `findVerifiedUserIdByPhone`, обновлены тесты (`StaffUseCasesTest`, `ManageStaffEndpointTest`, фикстура `ClassBookingUseCasesTest`), OpenAPI-спецификации (`identity-auth.yaml`, `business-core.yaml`, `companies.yaml`) и `README.md`.

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

- Этапы 0–7 завершены; можно переходить к интеграции фронтенда, настройке SMTP в `.env` для продакшена или планированию новых фич.
