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
- [x] **E2-D Notifications: push и SMS, напоминания об окончании абонемента**: T2-16…T2-20 — модуль `Notifications`, журнал `notifications_logs`, шлюзы `PushGatewayInterface` и `SmsGatewayInterface` с адаптерами логирования, сервис `NotificationSender`, регистрация и отзыв push-токенов (`POST/DELETE /api/v1/client/device-tokens`), джобы очереди `SendPushNotificationJob` и `SendSmsNotificationJob` с явным `company_id`, слушатели событий `VisitRegistered` и `MembershipRequestPaid`, ежедневная команда `notifications:send-expiring-reminders` (напоминания за 7 и 1 день с идемпотентностью) и расписание в `routes/console.php`

---

## Этап 2 — Client (приложение клиента)

### E2-E API для приложения клиента: мои абонементы, история
- **Статус:** `[ ]`
- **Цель:** реализовать эндпоинты личного кабинета клиента в мобильном приложении: просмотр списка активных и прошедших абонементов с остатком и сроком действия, история посещений и история покупок по залам.
- **ТЗ:** Бизнес-ТЗ 5.2; Тех. ТЗ 3.6, 7.1.
- **Критерий готовности:** `composer lint`, `composer analyse`, `composer deps`, `php artisan test` зелёные.

### T2-21 Эндпоинт абонементов клиента (`GET /api/v1/client/memberships`)
- **Статус:** `[x]`
- **Заметки:** метод `findAllClientIdsByUser` в `ClientLookupInterface` собирает ID карточек пользователя во всех залах. `listByClientIds` агрегирует абонементы по карточкам с фильтрацией `status=active` (статусы `active`, `pending`, `frozen`) или `status=past` (`expired`, `cancelled`), а также опциональным `company_id`. Возвращает `ClientMembershipItemDto` с названием типа абонемента и флагом `is_usable`.
- **Цель:** реализовать эндпоинт `GET /api/v1/client/memberships` для мобильного клиента с фильтрами по статусу (`active` / `past`) и компании, возвращающий список абонементов пользователя по всем залам с остатком занятий, сроками и названием типа.
- **ТЗ:** Бизнес-ТЗ 5.2; Тех. ТЗ 7.1.
- **Файлы:**
  - `app/Modules/Clients/Contracts/ClientLookupInterface.php`
  - `app/Modules/Clients/Infrastructure/EloquentClientLookup.php`
  - `app/Modules/Memberships/Contracts/ClientMembershipItemDto.php`
  - `app/Modules/Memberships/Domain/MembershipRepositoryInterface.php`
  - `app/Modules/Memberships/Infrastructure/EloquentMembershipRepository.php`
  - `app/Modules/Memberships/Application/ListMyMemberships.php`
  - `app/Modules/Memberships/Presentation/Requests/ListMyMembershipsRequest.php`
  - `app/Modules/Memberships/Presentation/ListMyMembershipsController.php`
  - `app/Modules/Memberships/Presentation/routes.php`
  - `tests/Feature/Memberships/ListMyMembershipsTest.php`
- **Шаги:**
  1. Добавить метод `findAllClientIdsByUser(int $userId): array` в `ClientLookupInterface` и реализовать в `EloquentClientLookup`.
  2. Создать DTO `ClientMembershipItemDto` в `Memberships/Contracts`.
  3. Добавить метод `listClientMembershipsByUser` в `MembershipRepositoryInterface` и реализовать в `EloquentMembershipRepository` (с подгрузкой `type` и фильтрами `status`, `company_id`).
  4. Создать Use Case `ListMyMemberships`.
  5. Создать `ListMyMembershipsRequest` (валидация `status: in:active,past`, `company_id: integer`) и контроллер `ListMyMembershipsController`.
  6. Зарегистрировать маршрут `GET /api/v1/client/memberships` в `Presentation/routes.php`.
  7. Написать Feature-тесты `ListMyMembershipsTest`.
- **Критерий готовности:** `composer lint`, `composer analyse`, `composer deps`, `php artisan test` зелёные.

### T2-22 Эндпоинт истории посещений клиента (`GET /api/v1/client/visits`)
- **Статус:** `[x]`
- **Заметки:** DTO `ClientVisitItemDto` в `Visits/Contracts`, метод `listForClients` с пагинацией и фильтрами по `company_id` и `status` в `VisitRepositoryInterface` и `EloquentVisitRepository`, Use Case `ListMyVisits` через `ClientLookupInterface::findAllClientIdsByUser`, контроллер `ListMyVisitsController` возвращает `data` и `meta: {page, per_page, total}`, маршрут `GET /api/v1/client/visits` под `auth:sanctum`.
- **Цель:** реализовать клиентский эндпоинт `GET /api/v1/client/visits` для мобильного приложения, возвращающий историю визитов пользователя по всем залам (с пагинацией, фильтром по залу/компании и статусу `completed` / `cancelled`).
- **ТЗ:** Бизнес-ТЗ 5.2; Тех. ТЗ 7.1.
- **Файлы:**
  - `app/Modules/Visits/Contracts/ClientVisitItemDto.php`
  - `app/Modules/Visits/Domain/VisitRepositoryInterface.php`
  - `app/Modules/Visits/Infrastructure/EloquentVisitRepository.php`
  - `app/Modules/Visits/Application/ListMyVisits.php`
  - `app/Modules/Visits/Presentation/Requests/ListMyVisitsRequest.php`
  - `app/Modules/Visits/Presentation/ListMyVisitsController.php`
  - `app/Modules/Visits/Presentation/routes.php`
  - `tests/Feature/Visits/ListMyVisitsTest.php`
- **Шаги:**
  1. Создать DTO `ClientVisitItemDto` (`id`, `companyId`, `gymId`, `clientId`, `membershipId`, `method`, `status`, `visitedAt`, `cancelReason`, `cancelledAt`) в `Visits/Contracts`.
  2. Добавить метод `listForClients` с пагинацией и фильтрами в `VisitRepositoryInterface` и `EloquentVisitRepository`.
  3. Создать Use Case `ListMyVisits` (получает карточки через `ClientLookupInterface::findAllClientIdsByUser`, запрашивает визиты).
  4. Создать `ListMyVisitsRequest` и контроллер `ListMyVisitsController`.
  5. Зарегистрировать маршрут `GET /api/v1/client/visits` под `auth:sanctum`.
  6. Написать Feature-тесты `ListMyVisitsTest`.
- **Критерий готовности:** `composer lint`, `composer analyse`, `composer deps`, `php artisan test` зелёные.

### T2-23 Эндпоинт истории покупок клиента (`GET /api/v1/client/purchases`)
- **Статус:** `[ ]`
- **Цель:** реализовать клиентский эндпоинт `GET /api/v1/client/purchases` для мобильного приложения, возвращающий историю покупок пользователя по всем его карточкам клиента во всех залах (с пагинацией, позициями чека `SaleItem`, отметкой об оплате `PaymentMark`, фильтрами по компании и статусу `completed` / `refunded`).
- **ТЗ:** Бизнес-ТЗ 5.2; Тех. ТЗ 7.1.
- **Файлы:**
  - `app/Modules/Sales/Domain/SaleRepositoryInterface.php`
  - `app/Modules/Sales/Infrastructure/EloquentSaleRepository.php`
  - `app/Modules/Sales/Application/ListMyPurchases.php`
  - `app/Modules/Sales/Presentation/Requests/ListMyPurchasesRequest.php`
  - `app/Modules/Sales/Presentation/ListMyPurchasesController.php`
  - `app/Modules/Sales/Presentation/routes.php`
  - `tests/Feature/Sales/ListMyPurchasesTest.php`
- **Шаги:**
  1. Добавить метод `listForClients(array $clientIds, ?int $companyId, ?string $status, int $page, int $perPage): array{items: list<Sale>, total: int}` в `SaleRepositoryInterface` и реализовать в `EloquentSaleRepository`.
  2. Создать Use Case `ListMyPurchases` (агрегирует ID клиентов через `ClientLookupInterface::findAllClientIdsByUser`, вызывает `listForClients`).
  3. Создать `ListMyPurchasesRequest` (валидация `page`, `per_page`, `company_id`, `status: in:completed,refunded`).
  4. Создать `ListMyPurchasesController` с сериализацией покупок через `RegisterSaleController::serializeSale` и мета-информацией пагинации.
  5. Зарегистрировать маршрут `GET /api/v1/client/purchases` в `Sales/Presentation/routes.php` под `auth:sanctum`.
  6. Написать Feature-тесты `ListMyPurchasesTest`.
- **Критерий готовности:** `composer lint`, `composer analyse`, `composer deps`, `php artisan test` зелёные.

5. [ ] **E2-E** API для приложения клиента: мои абонементы, история

## Этапы 3–5 (не раскрывать до завершения предыдущего этапа)
- Этап 3: публичный каталог, модерация, поиск, карта
- Этап 4: расписание и тренеры
- Этап 5: дневник тренировок
