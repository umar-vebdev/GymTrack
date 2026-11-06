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

**Статус этапа:** E3-A завершён; E3-B завершён; E3-C ожидает подтверждения плана задач разработчиком.
**ТЗ:** Бизнес-ТЗ 4.2, 7; Тех. ТЗ 3.1, 7.1, 14.

### E3-A — задачи (модуль Companies)

- [x] **T3-00b** Восстановлен стиль четырёх файлов очереди модерации после 31b6283; lint/analyse/test/deps зелёные.
- [x] **T3-01** (заметка: сущность содержит проверку полноты `isComplete()` для `submit`; `composer lint` падает на 10 чужих файлах — см. T3-00) Миграция `companies_gym_profiles` (`gym_id` unique, `description`, `address`, `latitude`, `longitude` nullable, `phone`, `working_hours` JSON, `amenities` JSON, `moderation_status`, `rejection_reason`, `published_at`), enum `ModerationStatus`, сущность `GymProfile` с переходами статусов (`update`→draft, `submit` с проверкой полноты, `approve`, `reject`), unit-тест. Проверка: `php artisan test --filter=GymProfileTest`.
- [x] **T3-00** Привести к стилю Pint 10 файлов, оставшихся после T2-21…T2-23 (`braces_position`, `single_line_empty_body`, `single_blank_line_at_eof`): `composer lint` должен проходить. Только форматирование.
- [x] **T3-02** (заметка: `save()` — upsert, `company_id` берётся из `gyms` без `CompanyScope`, чтобы админ платформы мог сохранять чужие витрины в T3-05) `GymProfileRepositoryInterface` + Eloquent-реализация + тест.
- [x] **T3-03** (заметка: зал чужой компании → 404 `NOT_FOUND` через `GymNotFound`; широта и долгота — только вместе; `PUT` на `pending` → 400) `UpdateGymProfile` + `PUT /business/gyms/{id}/profile` (только владелец; правка одобренной витрины → draft).
- [x] **T3-04** (заметка: профиль без записи в БД трактуется как пустой draft → «заполните данные» 400) `SubmitGymProfileForModeration` + эндпоинт отправки (статус pending).
- [x] **T3-05a** Use Case `ModerateGymProfile`, события `GymProfileApproved`/`GymProfileRejected` в `Companies/Contracts`, эндпоинт `POST /api/v1/platform/gyms/{gym}/moderate` (`decision: approve|reject`, `reason` при reject, проверка `is_platform_admin`).
- [x] **T3-05b** Очередь витрин для платформы: метод в `GymProfileRepositoryInterface` для выборки по статусу с пагинацией, эндпоинт `GET /api/v1/platform/gym-profiles?status=pending`.
- [x] **T3-06a** Хранение фото: `companies_gym_photos`, неизменяемый `GymPhoto`, репозиторий и модель с `CompanyScope`; явная фильтрация по компании и залу, стабильный порядок (`order`, `id`), создание только для зала указанной компании. Четыре теста; lint/analyse/deps чистые, 320 тестов зелёные.
- [x] **T3-06b1** Сценарий загрузки на public: блокировка строки зала, лимит 10 фото, порядок после последнего, единое правило сброса модерации в draft; pending запрещён; сбой БД откатывает запись и удаляет новый файл. 7 тестов сценария, полный набор 327; lint/analyse/deps зелёные.
- [x] **T3-06b2** `POST /api/v1/business/gyms/{gym}/photos`: Policy проверяет владельца и компанию зала; FormRequest принимает jpeg/png/webp до 5 МБ; ответ 201 с id/gym_id/url/order/created_at. Чужой зал → 404, без прав → 403, лимит/pending → 400. OpenAPI: `docs/api/gym-photos.yaml`. 10 HTTP-тестов; полный набор 337 тестов, lint/analyse/deps зелёные.
- [x] **T3-06c** `DELETE /api/v1/business/gyms/{gym}/photos/{photo}`: только владелец своего зала; чужая/отсутствующая фотография → 404, pending → 400, успех → 200 с `data: null`. Блокировка строки зала общая с загрузкой, сброс модерации в draft, откат БД и восстановление файла при частичном сбое хранилища. Если файл уже отсутствует, запись удаляется (D-009). OpenAPI обновлён; 10 новых тестов, всего 347; lint/analyse/test/deps зелёные.
- [x] **T3-07** Аудит модерации уже подключён через общий `AuditableEvent` → `AuditEventListener`; отдельные обработчики не нужны. Добавлены 4 сквозных теста реальных запросов: автор, компания, зал, время, причина; отказы/повтор не создают записи, прежние записи сохраняются при повторной модерации и работе с другой компанией. Всего 351 тест, lint/analyse/test/deps зелёные.

### E3-B — согласованный план (2026-11-04)

Разработчик поручил выбрать оптимальный вариант и начать реализацию. Правила ниже приняты (D-010); ТЗ не меняются.

Согласованные правила:

- Каталог показывает только витрины `approved`, явно включённые владельцем (`is_public = true`). Включить можно только одобренную витрину; выключить — в любом статусе. Правка содержимого/фото отключает публикацию и требует повторной модерации и включения.
- Район: nullable-строка `district` до 100 символов в витрине; без справочника и геокодирования. Пустое значение не совпадает с фильтром района. Фильтр — точное совпадение без учёта регистра, пробелы по краям удаляются.
- Публичные идентификаторы: ULID в `gyms.public_id` и `membership_types.public_id`; заполнение существующих строк, уникальность, генерация новых значений. В каталоге `id` — ULID; текущие закрытые API с числовыми ID не меняются.
- Фильтр цены: `price_min`/`price_max` — целые минимальные единицы; при любом ценовом фильтре `currency` обязателен. Зал подходит, если у компании есть хотя бы один активный тип абонемента нужной валюты в указанном включительном диапазоне. Пересчёта валют нет. Типы абонементов сейчас принадлежат компании и общие для её филиалов.
- Удобства: все перечисленные `amenities[]` должны присутствовать. Поиск `q` — подстрока названия без учёта регистра; специальные символы поиска трактуются буквально. Сортировка по публичному идентификатору, постраничная выдача (по умолчанию 20, максимум 100).
- `Catalog` получает данные только через Contracts модулей Companies и Memberships; общий JOIN между модулями запрещён. Черновики, причины отклонения, секрет QR, данные владельца и сотрудников в публичный ответ не попадают. Карта и георадиус остаются в E3-C.

Все задачи ниже закрываются только после lint/analyse/test/deps. Если реализация превысит ~5 рабочих файлов или ~200 строк, перед продолжением разбить задачу.

- [x] **T3-08** Отдельный обязательный уникальный `public_id` (ULID) у залов и типов абонементов: порционное заполнение старых записей, генерация новых через встроенный `HasUlids` только для public_id. Числовые первичные ключи и связи сохранены. Шесть тестов генерации, заполнения, отката и ограничений БД; всего 357 тестов, lint/analyse/test/deps зелёные.
- [x] **T3-09a** Добавлены district (nullable, до 100 символов) и is_public (false по умолчанию), сохранение/чтение через модель и репозиторий. `setPublication` включает только approved и выключает в любом статусе; `markContentChanged` отключает публикацию. Конструктор не допускает публичную неодобренную витрину; старые вызовы совместимы. 14 новых тестов и дополнительные проверки фото; всего 371 тест, lint/analyse/test/deps зелёные.
- [x] **T3-09b** Район добавлен в существующий PUT витрины, команду и сценарий; FormRequest проверяет nullable-строку до 100 символов. Ответ общего GymProfileResource содержит district/is_public. Правка отключает публикацию; попытка передать is_public не включает её. Проверены очистка района, пробелы, Unicode, неверный тип/длина без изменения опубликованной витрины, pending и права. OpenAPI: `docs/api/gym-profiles.yaml`. Всего 374 теста; lint/analyse/test/deps зелёные.
- [x] **T3-09c** `PATCH /api/v1/business/gyms/{gym}/publication`: обязательный boolean is_public, Policy проверяет владельца и компанию, сценарий сохраняет состояние в транзакции с блокировкой зала. Включение только approved, выключение в любом статусе; повтор допустим, содержимое не меняется. Ответ через GymProfileResource; OpenAPI дополнен. 15 новых тестов; всего 389, lint/analyse/test/deps зелёные. Выявлен отдельный шаг T3-09d для согласования старых сценариев с этой блокировкой.
- [x] **T3-09d** `UpdateGymProfile`, `SubmitGymProfileForModeration` и `ModerateGymProfile` переведены на транзакцию с `findByIdForUpdate` (в `EloquentGymRepository::findByIdForUpdate` добавлен `withoutGlobalScopes()` для согласованности с `findById` при модерации платформой); витрина читается только после блокировки строки зала, события аудита модерации отправляются после фиксации транзакции. Добавлено 6 тестов порядка блокировки, сохранности публикации и отката при сбое БД; всего 395 тестов, lint/analyse/test/deps зелёные.
- [x] **T3-10** Контракт публичных витрин `PublicGymQueryInterface`, DTO `PublicGymProfile` и `PublicGymFilter`, реализация `EloquentPublicGymQuery` и привязка в `CompaniesServiceProvider`: чтение только `approved` + `is_public = true` без `CompanyScope`, фильтры `query` (подстрока без учёта регистра с буквальной трактовкой `%`, `_`, `\`), `district` (точное совпадение без регистра с `trim`, пустой район не совпадает), `amenities` (все указанные), `companyIds` (пустой массив возвращает 0 результатов без запросов к БД), пагинация по `gyms.public_id` и пакетная загрузка фото в порядке `(order, id)` без N+1 и без приватных полей. 6 тестов в `PublicGymQueryTest`; всего 401 тест, lint/analyse/test/deps зелёные.
- [x] **T3-11** Контракт актуальных абонементов `MembershipCatalogQueryInterface`, DTO `PublicMembershipType` (только публичный `public_id`, название, цена в минимальных единицах, валюта и действующие правила без внутренних `id`/`company_id`), реализация `EloquentMembershipCatalogQuery` и привязка в `MembershipsServiceProvider`: список активных типов компании по `public_id ASC` без `CompanyScope` и выборка `company_id` по активным типам во включительном диапазоне цен заданной валюты без смешивания валют; восстановлен `single_blank_line_at_eof` после `e1900dc`. 3 теста в `MembershipCatalogQueryTest`; всего 404 теста, lint/analyse/test/deps зелёные.
- [x] **T3-12** Модуль `Catalog` (`CatalogServiceProvider`, `SearchGyms`, `SearchGymsQuery`, `routes.php`): объединение контрактов `MembershipCatalogQueryInterface` и `PublicGymQueryInterface`, защита от утечки залов при пустом ценовом результате и проверка отсутствия зависимостей от внутренних классов чужих модулей (восстановлено форматирование Pint у 3 файлов после `edbf78c`). 5 тестов в `SearchGymsTest`; всего 409 тестов, lint/analyse/test/deps зелёные.
- [x] **T3-13** `GET /api/v1/public/gyms` без авторизации: `SearchGymsRequest` (валидация `q`, `district`, `amenities[]`, `price_min`, `price_max`, обязательность `currency` при ценовом фильтре, `price_max >= price_min`, `page`/`per_page`), `ListPublicGymsController`, `PublicGymResource` (`id` — ULID, без внутренних `gym_id`/`company_id` и приватных полей), OpenAPI `docs/api/public-catalog.yaml`. 3 теста в `ListPublicGymsTest`; всего 412 тестов, lint/analyse/test/deps зелёные.
- [x] **T3-14a** Сценарий публичной карточки `GetPublicGym` и DTO `PublicGymCardResult`: поиск опубликованной витрины по ULID через `PublicGymQueryInterface::findByPublicId`, подгрузка активных типов абонементов компании через `MembershipCatalogQueryInterface::listActiveForCompany` только после проверки публикации, мгновенное скрытие карточки при выключении или правке витрины (восстановлено форматирование Pint у 4 файлов после `d3511dc`). 3 теста в `GetPublicGymTest`; всего 415 тестов, lint/analyse/test/deps зелёные.
- [x] **T3-14b** `GET /api/v1/public/gyms/{publicId}` без авторизации: `ShowPublicGymController`, `PublicGymCardResource` (поля витрины + `membership_types` с публичными `id` ULID, ценами и правилами без внутренних `gym_id`/`company_id` и секретов; скрытая/неизвестная витрина → `404 NOT_FOUND`), дополнен OpenAPI `docs/api/public-catalog.yaml`. 3 теста в `ShowPublicGymTest`; всего 418 тестов, lint/analyse/test/deps зелёные.

### E3-C — Карта и гео-поиск (предлагаемый план на подтверждение)

- [ ] **T3-15 — Гео-фильтр прямоугольной области карты (`bounds`) и радиуса (`lat`, `lng`, `radius_km`) в `PublicGymFilter` и `EloquentPublicGymQuery`.** ТЗ: [Бизнес 4.2, 7](specs/GymTrack_TZ_v2.md), [Техническое 3.5, 6.3](specs/GymTrack_Technical_TZ.md).
  - Файлы: `PublicGymFilter.php`, `EloquentPublicGymQuery.php`, `PublicGymQueryTest.php`.
  - Шаги: 1. Расширить `PublicGymFilter` параметрами гео-поиска: прямоугольник карты (`swLat`, `swLng`, `neLat`, `neLng`) и/или точка с радиусом (`lat`, `lng`, `radiusKm`). 2. В `EloquentPublicGymQuery` при активном гео-фильтре отбирать только опубликованные витрины с непустыми координатами (`latitude IS NOT NULL AND longitude IS NOT NULL`), попадающие в прямоугольник и/или радиус (формула гаверсинуса). 3. Покрыть unit-тестами в `PublicGymQueryTest`.
  - Готовность: `docker compose exec -T app php artisan test --filter=PublicGymQueryTest` — залы без координат и за пределами радиуса/области отсекаются, подходящие возвращаются.
- [ ] **T3-16 — Поддержка гео-параметров в сценарии `SearchGyms` и публичном API `GET /api/v1/public/gyms`.** ТЗ: [Бизнес 7](specs/GymTrack_TZ_v2.md), [Техническое 7.1–7.2](specs/GymTrack_Technical_TZ.md).
  - Файлы: `SearchGymsQuery.php`, `SearchGyms.php`, `SearchGymsRequest.php`, `ListPublicGymsController.php`, `docs/api/public-catalog.yaml`, `ListPublicGymsTest.php`.
  - Шаги: 1. Пробросить гео-параметры (`lat`, `lng`, `radius_km`, `sw_lat`, `sw_lng`, `ne_lat`, `ne_lng`) через `SearchGymsQuery` и `SearchGyms`. 2. Валидировать комплектность (`lat`/`lng`/`radius_km` передаются вместе; `sw_lat`/`sw_lng`/`ne_lat`/`ne_lng` передаются вместе, `sw_lat <= ne_lat`, `sw_lng <= ne_lng`, допустимые диапазоны широт `[-90, 90]`, долгот `[-180, 180]`, `radius_km` `(0, 100]`). 3. Дополнить OpenAPI и проверить тестами в `ListPublicGymsTest`.
  - Готовность: `docker compose exec -T app php artisan test --filter=ListPublicGymsTest` — фильтрация по радиусу и области карты работает совместно с остальными фильтрами, неполные/неверные гео-параметры дают 422.

---

## Этапы 4–5 (не раскрывать до завершения предыдущего этапа)
- Этап 4: расписание и тренеры
- Этап 5: дневник тренировок
