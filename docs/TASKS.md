# GymTrack — чек-лист задач

**Текущий этап:** 1 (Business: касса, продажи, подписка зала, аналитика)
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

---

## Этап 1 — Business (Billing, Analytics)

7. **E1-G Billing**: тарифы платформы (`PlatformPlan`), подписка компании (`Subscription`), автоматический пробный период 14 дней (`TrialPolicy`), ежедневный перевод истёкших подписок, режим «только чтение» после окончания подписки/пробного периода
   > Раскрытие эпика подтверждено разработчиком 2026-10-28.

   - [x] **T1-36** Домен и инфраструктура тарифов платформы (`PlatformPlan`): перечисление `BillingPeriod` (`month` | `year`), сущность `PlatformPlan` (название, период, цена `Money`, лимиты `maxGyms` и `maxStaff`, флаг `isActive`), миграция `billing_plans` (с `CHECK >= 0`), `PlatformPlanModel`, `PlatformPlanRepositoryInterface`, `EloquentPlatformPlanRepository`, `BillingServiceProvider` (+ регистрация в `bootstrap/providers.php`), unit/repo-тест.
     - Заметки: таблица `billing_plans` с проверками целостности `price_minor >= 0`, `max_gyms >= 1`, `max_staff >= 1`.

   - [x] **T1-37** Домен и инфраструктура подписки компании (`Subscription`, `TrialPolicy`): статусы `trial` | `active` | `expired` (`SubscriptionStatus`), `TrialPolicy` (14 дней по умолчанию — Бизнес-ТЗ 1.1, 4.1), сущность `Subscription` (проверка активности / режима «только чтение», метод `expireIfDue()`, продление по тарифу `activatePlan()`), миграция `billing_subscriptions`, `SubscriptionModel`, `SubscriptionRepositoryInterface`, `EloquentSubscriptionRepository`, unit/repo-тест.
     - Заметки: таблица `billing_subscriptions` с уникальным `company_id` (одна подписка на компанию) и индексом `(status, ends_at)` для ежедневного планировщика.

   - [x] **T1-38a** Автоматический старт пробного периода при регистрации компании и Use Cases чтения подписки/тарифов: событие `CompanyRegistered` в `Companies\Contracts` (отправляется из `RegisterCompany`), слушатель `StartTrialOnCompanyRegistered` в `Billing\Application\Listeners` (подписка в `BillingServiceProvider`), сценарии `ListPlatformPlans` и `GetCompanySubscription`.
     - Заметки: связь модулей `Companies` → `Billing` реализована строго через доменное событие `CompanyRegistered` в `Companies\Contracts` (по образцу `PhoneVerified`).

   - [~] **T1-38b** HTTP-эндпоинты тарифов и статуса подписки зала (`GET /api/v1/business/billing/plans`, `GET /api/v1/business/billing/subscription`) и Feature-тесты: `ListPlatformPlansController`, `GetCompanySubscriptionController`, `Presentation/routes.php`, `tests/Feature/Billing/TrialAndSubscriptionStatusTest.php`.
     - Ссылки: Бизнес-ТЗ 1.1, 4.1; Тех. ТЗ 7.1, 7.2, 8.
     - Файлы: `app/Modules/Billing/Presentation/ListPlatformPlansController.php`, `app/Modules/Billing/Presentation/GetCompanySubscriptionController.php`, `app/Modules/Billing/Presentation/routes.php`, `tests/Feature/Billing/TrialAndSubscriptionStatusTest.php`.
     - Готово когда: Feature-тесты проверяют, что при регистрации компании (`POST /api/v1/business/companies`) автоматически создаётся пробный период на 14 дней, а `GET /api/v1/business/billing/subscription` и `GET /api/v1/business/billing/plans` возвращают статус подписки и доступные тарифы платформы.
   - [ ] **T1-39** Подтверждение оплаты подписки админом платформы и ежедневная задача проверки истёкших подписок: `ActivateCompanySubscription` (`POST /api/v1/platform/companies/{id}/subscription/activate` + аудируемое событие `SubscriptionActivated`), консольная команда `billing:expire-due` (ежедневно в `routes/console.php`, Тех. ТЗ 9.4), Feature-тесты.
   - [ ] **T1-40** Режим «только чтение» при истёкшем пробном периоде или подписке (Бизнес-ТЗ 4.1, 12.8): контракт `SubscriptionAccessInterface` и middleware `EnsureActiveCompanySubscription`, блокирующий мутирующие запросы (`POST`/`PUT`/`PATCH`/`DELETE`) к `/api/v1/business/*` (кроме чтения `GET` и создания компании) кодом `403 SUBSCRIPTION_EXPIRED`, Feature-тест по критерию приёмки 12.8.

8. [ ] **E1-H Analytics**: выручка по отметкам оплаты, посещения, клиенты, популярность

## Этап 2 — Client (эпики)
- [ ] **E2-A** Регистрация клиента и связывание с карточками зала
- [ ] **E2-B** Visits: динамический QR, отметка посещения, правила `CheckInRule`, идемпотентность
- [ ] **E2-C** Заявки на абонемент (Sales) и их автоотмена
- [ ] **E2-D** Notifications: push и SMS, напоминания об окончании абонемента
- [ ] **E2-E** API для приложения клиента: мои абонементы, история

## Этапы 3–5 (не раскрывать до завершения предыдущего этапа)
- Этап 3: публичный каталог, модерация, поиск, карта
- Этап 4: расписание и тренеры
- Этап 5: дневник тренировок
