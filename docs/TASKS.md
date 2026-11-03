# GymTrack — чек-лист задач

**Текущий этап:** 3 (Каталог: публичный каталог залов, модерация витрин платформой, поиск и карта)
**Статусы:** `[ ]` не начата · `[~]` в работе · `[x]` готова · `[!]` заблокирована (причина в заметках)

## Правила ведения файла

- Нумерация: `T{этап}-{номер}`, подзадачи `T1-07a`, `T1-07b`.
- Завершённые задачи сворачиваются в одну строку (см. «Готово»), подробности остаются в коммите.
- Подробное описание нужно только для активной и будущих задач **текущего этапа**. Задачи следующих этапов хранятся как эпики и раскрываются перед стартом.
- Одна задача = одна вещь, до ~5 файлов и ~200 строк кода, проверяется одной командой.

---

## Готово

- [x] T0-01 Инициализация проекта Laravel и репозитория
- [x] T0-02 Docker Compose для локальной разработки
- [x] T0-03 Подключение БД и Redis, эндпоинт health-check
- [x] T0-04 Каркас директорий модулей и Shared
- [x] T0-05 Laravel Pint (стиль кода)
- [x] T0-06 Larastan (статический анализ)
- [x] T0-07 Pest и первый тест
- [x] T0-08 Deptrac и архитектурные тесты
- [x] T0-09 Shared Kernel: Money
- [x] T0-10 Shared Kernel: CompanyId, Clock, базовое доменное событие
- [x] T0-11 Единый формат ошибок API и префикс версии
- [x] T0-12 Установка Sanctum
- [x] T0-13 CI-конвейер
- [x] **E1-A Identity**: регистрация, вход, OTP, токены
- [x] **E1-B Companies**: компания, зал, сотрудники, роли, мультиарендность
- [x] **E1-C Audit**: неизменяемый журнал, подписка на события
- [x] **E1-D Clients (CRM)**: T1-11…T1-15 — создание, поиск, связывание по `PhoneVerified` (D-002), объединение дубликатов (только владелец; перенос данных абонементов/посещений добавить при появлении Memberships/Visits; аудит слияния — отдельной задачей)
- [x] **E1-E Memberships**: T1-16…T1-28 — типы абонементов, правила (`MembershipRules`), богатая сущность `Membership`, стратегии списания, выдача (`IssueMembership`), контракт выбора (`FindUsableMembership`), заморозка/разморозка, отмена владельцем + аудит, список абонементов клиента, ежедневное завершение истёкших (`memberships:expire-due`), список истекающих в ближайшие 7 дней (`GET /memberships/expiring`)
- [x] **E1-F Sales**: T1-29…T1-35b — товары (`Product`), управление товарами, домен и инфраструктура продаж (`Sale`, `SaleItem`, `PaymentMark`), касса (`RegisterSale` с атомарной выдачей абонемента и списанием остатков + `Idempotency-Key` + `PaymentMarked`), история покупок клиента и список продаж зала, возврат продажи владельцем (`RefundSale` с возвратом остатков и отменой выданного абонемента через `MembershipCancellerInterface` + `SaleRefunded`)
- [x] **E1-G Billing**: T1-36…T1-40 — тарифы платформы (`PlatformPlan`), подписка компании (`Subscription`), автоматический пробный период 14 дней (`TrialPolicy`, событие `CompanyRegistered` → `StartTrialOnCompanyRegistered`), просмотр тарифов и подписки, активация тарифа админом платформы (`POST /api/v1/platform/companies/{id}/subscription/activate` + `SubscriptionActivated`), ежедневная команда `billing:expire-due`, режим «только чтение» при истёкшем пробном периоде/подписке (`EnsureActiveCompanySubscription`)
- [x] **E1-H Analytics**: T1-41…T1-43 — контракты аналитики продаж (`SalesAnalyticsQueryInterface`, `RevenueSummary`, `ItemPopularity`) и клиентов (`ClientsAnalyticsQueryInterface`, `ClientsAnalyticsSummary`), модуль `Analytics` (`GetRevenueReport`, `GetOverviewAnalytics`), эндпоинты `GET /api/v1/business/analytics/revenue` и `GET /api/v1/business/analytics/overview` (доступ только владельцу — критерий приёмки 12.7)
- [x] **E2-A Регистрация клиента и связывание с карточками зала**: T2-01…T2-03 — поля `birth_date` и `personal_data_consented_at`, доменное правило `ClientRegistrationPolicy` (18+ и согласие), регистрация `POST /api/v1/client/auth/register` с отправкой OTP и автоматическим связыванием карточек залов после подтверждения номера (критерий 12.9), профиль `GET /api/v1/client/profile` и обезличивание аккаунта по запросу `DELETE /api/v1/client/profile`
- [x] **E2-B Visits: динамический QR, отметка посещения, правила `CheckInRule`, идемпотентность**: T2-04…T2-10b — зашифрованный `qr_secret` зала, динамический HMAC QR (`HmacQrTokenService`, 60 с + предыдущий слот, `GET /api/v1/business/gyms/{id}/qr`), сущность `Visit` и `EloquentVisitRepository`, контракт `MembershipVisitConsumerInterface` (`ConsumeMembershipVisit`, `Membership::restoreVisit`), цепочка правил `CheckInRule` (D-007), отметка клиентом по QR (`POST /api/v1/client/visits/check-in`), ручная отметка сотрудником с поддержкой `visited_at` задним числом (`POST /api/v1/business/clients/{id}/visits`), отмена ошибочной отметки в течение 24 ч (`POST /api/v1/business/visits/{id}/cancel`), списки посещений клиента и компании, контракт аналитики `VisitsAnalyticsQueryInterface` (посещения по способам/дням и «спящие» клиенты в `GET /api/v1/business/analytics/overview`)
- [x] **T2-00 Справочник валют (`currencies`), валюта компании (`currency_id`) и смена валюты зала (решение D-003 / D-008)**: T2-00a…T2-00c — справочник `currencies` (TJS id=1 по умолчанию), внешний ключ `companies.currency_id`, эндпоинты `GET /api/v1/business/currencies` и `PATCH /api/v1/business/company/currency`, контракт `CompanyCurrencyLookupInterface` и автоматическое использование валюты компании при создании абонементов и товаров
- [x] **E2-C Заявки на абонемент (Sales) и их автоотмена**: T2-11…T2-15 — оформление заявки клиентом в приложении (`POST /client/membership-requests` с `Idempotency-Key`), просмотр заявок ресепшеном, приём оплаты и подтверждение с атомарной выдачей абонемента и отметкой `PaymentMark`, ручная отмена и фоновая автоотмена просроченных заявок через 24 часа (`sales:expire-requests`)
- [x] **E2-D Notifications: push и SMS, напоминания об окончании абонемента**: T2-16…T2-20 — модуль `Notifications`, журнал `notifications_logs`, шлюзы `PushGatewayInterface` и `SmsGatewayInterface` с адаптерами логирования, сервис `NotificationSender`, регистрация и отзыв push-токенов (`POST/DELETE /api/v1/client/device-tokens`), джобы очереди `SendPushNotificationJob` и `SendSmsNotificationJob` с явным `company_id`, слушатели событий `VisitRegistered` и `MembershipRequestPaid`, ежедневная команда `notifications:send-expiring-reminders` (напоминания за 7 и 1 день с идемпотентностью) и расписание в `routes/console.php`
- [x] **E2-E API для приложения клиента: мои абонементы, история**: T2-21…T2-23 — эндпоинты мобильного приложения клиента (`GET /api/v1/client/memberships`, `GET /api/v1/client/visits`, `GET /api/v1/client/purchases`) с мультиарендной агрегацией по всем карточкам клиента пользователя (`ClientLookupInterface::findAllClientIdsByUser`), пагинацией, фильтрацией по компании и статусу

---

## Этап 3 — Публичный каталог залов и модерация витрин

**Статус этапа:** E3-A раскрыт (подтверждён разработчиком 2026-11-03), E3-B и E3-C — эпики.
**ТЗ:** Бизнес-ТЗ 4.2, 7; Тех. ТЗ 3.1, 7.1, 14.

### E3-A — задачи (модуль Companies)

- [x] **T3-01** (заметка: сущность содержит проверку полноты `isComplete()` для `submit`; `composer lint` падает на 10 чужих файлах — см. T3-00) Миграция `companies_gym_profiles` (`gym_id` unique, `description`, `address`, `latitude`, `longitude` nullable, `phone`, `working_hours` JSON, `amenities` JSON, `moderation_status`, `rejection_reason`, `published_at`), enum `ModerationStatus`, сущность `GymProfile` с переходами статусов (`update`→draft, `submit` с проверкой полноты, `approve`, `reject`), unit-тест. Проверка: `php artisan test --filter=GymProfileTest`.
- [x] **T3-00** Привести к стилю Pint 10 файлов, оставшихся после T2-21…T2-23 (`braces_position`, `single_line_empty_body`, `single_blank_line_at_eof`): `composer lint` должен проходить. Только форматирование.
- [x] **T3-02** (заметка: `save()` — upsert, `company_id` берётся из `gyms` без `CompanyScope`, чтобы админ платформы мог сохранять чужие витрины в T3-05) `GymProfileRepositoryInterface` + Eloquent-реализация + тест.
- [x] **T3-03** (заметка: зал чужой компании → 404 `NOT_FOUND` через `GymNotFound`; широта и долгота — только вместе; `PUT` на `pending` → 400) `UpdateGymProfile` + `PUT /business/gyms/{id}/profile` (только владелец; правка одобренной витрины → draft).
- [x] **T3-04** (заметка: профиль без записи в БД трактуется как пустой draft → «заполните данные» 400) `SubmitGymProfileForModeration` + эндпоинт отправки (статус pending).
- [ ] **T3-05** `ModerateGymProfile` + `POST /platform/gyms/{id}/moderate` + `GET /platform/gym-profiles?status=pending`; события `GymProfileApproved`/`GymProfileRejected`.
- [ ] **T3-06** Фото зала: `companies_gym_photos`, загрузка/удаление (диск `public`, лимиты).
- [ ] **T3-07** Аудит событий модерации (слушатели в `Audit`).

### Эпики этапа 3 (предложение для подтверждения разработчиком)
- **E3-A Модерация залов и публичные витрины (Companies & Platform)**: витрина зала (`gym_profiles` / статус модерации: `draft`, `pending`, `approved`, `rejected`), подача заявки владельцем, одобрение/отклонение админом платформы (`/api/v1/platform/gyms/{id}/moderate`), загрузка/управление фото зала.
- **E3-B Модуль Catalog и публичный API**: модуль `app/Modules/Catalog`, эндпоинт каталога `GET /api/v1/public/gyms` (поиск по названию, фильтры по удобствам, цене, району, пагинация), эндпоинт карточки зала `GET /api/v1/public/gyms/{id}` с актуальными ценами и абонементами.
- **E3-C Карта и гео-поиск (Catalog)**: координаты залов (latitude, longitude), фильтрация по радиусу/координатам для отображения на карте.

---

## Этапы 4–5 (не раскрывать до завершения предыдущего этапа)
- Этап 4: расписание и тренеры
- Этап 5: дневник тренировок
