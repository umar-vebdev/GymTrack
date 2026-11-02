# GymTrack — чек-лист задач

**Текущий этап:** 2 (Client: приложение клиента, динамический QR и посещения, заявки, уведомления)
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

---

## Этап 2 — Client (приложение клиента)

### E2-D Notifications: push и SMS, напоминания об окончании абонемента
- **Статус:** `[ ]`
- **Цель:** реализовать модуль `Notifications` с очередями для push и SMS, подпиской на события (`VisitRegistered`, `MembershipRequestPaid`), управлением push-токенами клиентских устройств и ежедневной отправкой напоминаний об окончании абонементов (за 7 и 1 день) с идемпотентностью.
- **ТЗ:** Бизнес-ТЗ 5.3, 5.4, 10, 11, 12.1, 12.5; Тех. ТЗ 3.6, 3.7, 4.1, 4.4, 9.4.
- **Критерий готовности:** `composer lint`, `composer analyse`, `composer deps`, `php artisan test` зелёные.

### T2-16 Каркас модуля `Notifications`, журнал `notifications_logs`, шлюзы `PushGateway`, `SmsGateway` и сервис `NotificationSender`
- **Статус:** `[x]`
- **Заметки:** таблица `notifications_logs` хранит `entity_type` и `entity_id` для однозначной проверки идемпотентности через `alreadySent()`. Чистые доменные интерфейсы `PushGatewayInterface` и `SmsGatewayInterface` отделены от адаптеров `LogPushGateway` и `LogNotificationsSmsGateway`. Сервис `NotificationSender` координирует отправку, регистрирует статус `pending`, переводит в `sent` с фиксацией времени или в `failed` с текстом ошибки.
- **Цель:** создать структуру модуля `Notifications` (Domain, Application, Infrastructure, Presentation, `NotificationsServiceProvider`), таблицу `notifications_logs`, интерфейсы шлюзов и адаптеры логирования (`LogPushGateway`, `LogNotificationsSmsGateway`), сервис отправки `NotificationSender`.
- **ТЗ:** Тех. ТЗ 3.6, 4.1, 4.4; Бизнес-ТЗ 5.3.
- **Файлы:**
  - `app/Modules/Notifications/NotificationsServiceProvider.php`
  - `app/Modules/Notifications/Infrastructure/Migrations/2026_11_02_000000_create_notifications_logs_table.php`
  - `app/Modules/Notifications/Domain/NotificationChannel.php`
  - `app/Modules/Notifications/Domain/NotificationType.php`
  - `app/Modules/Notifications/Domain/NotificationStatus.php`
  - `app/Modules/Notifications/Domain/NotificationLogEntry.php`
  - `app/Modules/Notifications/Domain/NotificationLogRepositoryInterface.php`
  - `app/Modules/Notifications/Domain/PushGatewayInterface.php`
  - `app/Modules/Notifications/Domain/SmsGatewayInterface.php`
  - `app/Modules/Notifications/Infrastructure/LogPushGateway.php`
  - `app/Modules/Notifications/Infrastructure/LogNotificationsSmsGateway.php`
  - `app/Modules/Notifications/Infrastructure/Persistence/NotificationLogModel.php`
  - `app/Modules/Notifications/Infrastructure/EloquentNotificationLogRepository.php`
  - `app/Modules/Notifications/Application/NotificationSender.php`
  - `bootstrap/providers.php`
  - `tests/Unit/Notifications/NotificationSenderTest.php`
  - `tests/Unit/Notifications/EloquentNotificationLogRepositoryTest.php`
- **Критерий готовности:** `composer lint`, `composer analyse`, `composer deps`, `php artisan test` зелёные.

### T2-17 Регистрация и отзыв push-токенов клиентских устройств (`POST/DELETE /api/v1/client/device-tokens`)
- **Статус:** `[x]`
- **Заметки:** таблица `notifications_device_tokens` использует уникальный составной индекс `(user_id, token)`. Регистрация токена выполняется через upsert (при повторной отправке обновляется платформа и время активности), отзыв удаляет запись для текущего пользователя. Маршруты защищены `auth:sanctum`.
- **Цель:** реализовать хранение токенов мобильных устройств клиентов для push-уведомлений, эндпоинты `POST /api/v1/client/device-tokens` (сохранение/обновление токена устройства) и `DELETE /api/v1/client/device-tokens` (отзыв токена при выходе).
- **ТЗ:** Тех. ТЗ 3.6, 7.1; Бизнес-ТЗ 5.3.
- **Файлы:**
  - `app/Modules/Notifications/Infrastructure/Migrations/2026_11_02_000001_create_notifications_device_tokens_table.php`
  - `app/Modules/Notifications/Infrastructure/Persistence/DeviceTokenModel.php`
  - `app/Modules/Notifications/Domain/DeviceTokenRepositoryInterface.php`
  - `app/Modules/Notifications/Infrastructure/EloquentDeviceTokenRepository.php`
  - `app/Modules/Notifications/Application/RegisterDeviceToken.php`
  - `app/Modules/Notifications/Application/UnregisterDeviceToken.php`
  - `app/Modules/Notifications/Presentation/Requests/RegisterDeviceTokenRequest.php`
  - `app/Modules/Notifications/Presentation/Requests/UnregisterDeviceTokenRequest.php`
  - `app/Modules/Notifications/Presentation/DeviceTokensController.php`
  - `app/Modules/Notifications/Presentation/routes.php`
  - `tests/Feature/Notifications/DeviceTokensEndpointTest.php`
- **Шаги:**
  1. Создать миграцию `notifications_device_tokens` (`id`, `user_id`, `token`, `platform`, `created_at`, `updated_at`) с уникальным индексом `(user_id, token)`.
  2. Создать репозиторий `DeviceTokenRepositoryInterface` и реализацию `EloquentDeviceTokenRepository`.
  3. Создать Use Cases `RegisterDeviceToken` (идемпотентный upsert) и `UnregisterDeviceToken`.
  4. Создать FormRequests и контроллер `DeviceTokensController` с маршрутами в группе `client`.
  5. Написать Feature-тесты на регистрацию и удаление токенов.
- **Критерий готовности:** `composer lint`, `composer analyse`, `composer deps`, `php artisan test` зелёные.

### T2-18 Очереди и фоновые задачи отправки уведомлений (`SendPushNotificationJob`, `SendSmsNotificationJob`)
- **Статус:** `[x]`
- **Заметки:** обе фоновые задачи реализуют `ShouldQueue`, принимают `company_id` явно для сохранения мультиарендной изоляции в очереди без HTTP-контекста. `SendPushNotificationJob` поддерживает прямую отправку по `recipientToken` или автоматический поиск всех активных токенов пользователя через `DeviceTokenRepositoryInterface`. Отправка фиксируется в `notifications_logs`.
- **Цель:** реализовать асинхронную отправку push и SMS через очередь (`ShouldQueue`), с явной передачей `company_id` (Тех. ТЗ 9.4), отправкой push по зарегистрированным токенам пользователя и фиксацией результата в `notifications_logs`.
- **ТЗ:** Тех. ТЗ 3.6, 9.4.
- **Файлы:**
  - `app/Modules/Notifications/Application/Jobs/SendPushNotificationJob.php`
  - `app/Modules/Notifications/Application/Jobs/SendSmsNotificationJob.php`
  - `tests/Unit/Notifications/NotificationJobsTest.php`
- **Шаги:**
  1. Создать джобу `SendPushNotificationJob` (`ShouldQueue`, явный `companyId`, поиск токенов пользователя через `DeviceTokenRepositoryInterface`, отправка через `NotificationSender::sendPush`).
  2. Создать джобу `SendSmsNotificationJob` (`ShouldQueue`, явный `companyId`, отправка через `NotificationSender::sendSms`).
  3. Написать Unit/Feature тесты на выполнение джоб и корректное логирование в `notifications_logs`.
- **Критерий готовности:** `composer lint`, `composer analyse`, `composer deps`, `php artisan test` зелёные.

### T2-19 Слушатели доменных событий: подтверждение визита (`VisitRegistered`) и оплата заявки (`MembershipRequestPaid`)
- **Статус:** `[ ]`
- **Цель:** реализовать подписку модуля `Notifications` на события `VisitRegistered` и `MembershipRequestPaid`, получение контактов клиента через `ClientLookupInterface` и постановку уведомлений в очередь (`SendPushNotificationJob` и `SendSmsNotificationJob`).
- **ТЗ:** Бизнес-ТЗ 5.3, 5.4, 12.1, 12.5; Тех. ТЗ 3.5, 3.7.
- **Файлы:**
  - `app/Modules/Clients/Contracts/ClientRecipientDto.php`
  - `app/Modules/Clients/Contracts/ClientLookupInterface.php`
  - `app/Modules/Clients/Infrastructure/EloquentClientLookup.php`
  - `app/Modules/Notifications/Application/Listeners/SendVisitNotificationListener.php`
  - `app/Modules/Notifications/Application/Listeners/SendMembershipRequestPaidNotificationListener.php`
  - `app/Modules/Notifications/NotificationsServiceProvider.php`
  - `tests/Feature/Notifications/NotificationEventListenersTest.php`
- **Шаги:**
  1. Создать DTO `ClientRecipientDto` (`clientId`, `userId`, `phone`, `name`) в `Clients\Contracts` и добавить метод `findRecipient(int $clientId)` в `ClientLookupInterface`.
  2. Реализовать `findRecipient` в `EloquentClientLookup`.
  3. Реализовать слушатель `SendVisitNotificationListener` (на `VisitRegistered`), формирующий текст «Посещение подтверждено» и ставящий задачи в очередь.
  4. Реализовать слушатель `SendMembershipRequestPaidNotificationListener` (на `MembershipRequestPaid`), отправляющий «Абонемент активирован. Вы можете отметить посещение».
  5. Зарегистрировать слушатели событий в `NotificationsServiceProvider`.
  6. Написать Feature-тесты `NotificationEventListenersTest` на генерацию уведомлений при наступлении событий.
- **Критерий готовности:** `composer lint`, `composer analyse`, `composer deps`, `php artisan test` зелёные.
- [ ] **T2-20** Ежедневные напоминания об окончании абонемента за 7 и 1 день (`notifications:send-expiring-reminders`, контракт `MembershipLookupInterface`, расписание в `routes/console.php`, Feature-тесты).

5. [ ] **E2-E** API для приложения клиента: мои абонементы, история

## Этапы 3–5 (не раскрывать до завершения предыдущего этапа)
- Этап 3: публичный каталог, модерация, поиск, карта
- Этап 4: расписание и тренеры
- Этап 5: дневник тренировок
