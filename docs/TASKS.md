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
- [x] **E1-G Billing**: T1-36…T1-40 — тарифы платформы (`PlatformPlan`), подписка компании (`Subscription`), автоматический пробный период 14 дней (`TrialPolicy`, событие `CompanyRegistered` → `StartTrialOnCompanyRegistered`), просмотр тарифов и подписки, активация тарифа админом платформы (`POST /api/v1/platform/companies/{id}/subscription/activate` + `SubscriptionActivated`), ежедневная команда `billing:expire-due`, режим «только чтение» при истёкшем пробном периоде/подписке (`EnsureActiveCompanySubscription`)

---

## Этап 1 — Business (Analytics)

8. [~] **E1-H Analytics**: отчёты для владельца по отметкам оплаты (выручка в разрезе абонементов/товаров и способов оплаты), популярности абонементов и товаров, новым и постоянным клиентам (Бизнес-ТЗ 4.7, 12.7; Тех. ТЗ 3.6, 3.7 — чтение строго через `Contracts` других модулей)

- [x] **T1-41** Контракт аналитики продаж (`SalesAnalyticsQueryInterface`) и его реализация (`RevenueSummary`, `ItemPopularity`, `EloquentSalesAnalyticsQuery`)
- [x] **T1-42** Контракт аналитики клиентов (`ClientsAnalyticsQueryInterface`) и его реализация (`ClientsAnalyticsSummary`, `EloquentClientsAnalyticsQuery`)

### T1-43 Модуль `Analytics`: Use Cases и эндпоинты отчётов для владельца
- **Статус:** `[~]`
- **Цель:** реализовать модуль `Analytics` с эндпоинтами `GET /api/v1/business/analytics/revenue` и `GET /api/v1/business/analytics/overview`, доступными только владельцу компании (критерий приёмки 12.7).
- **ТЗ:** Бизнес-ТЗ 2, 4.7, 12.6, 12.7; Тех. ТЗ 3.6, 3.7, 7.1, 7.2, 8.
- **Файлы:**
  - `app/Modules/Analytics/Application/GetRevenueReport.php`
  - `app/Modules/Analytics/Application/GetOverviewAnalytics.php`
  - `app/Modules/Analytics/Presentation/Requests/AnalyticsPeriodRequest.php`
  - `app/Modules/Analytics/Presentation/RevenueReportController.php`
  - `app/Modules/Analytics/Presentation/OverviewAnalyticsController.php`
  - `app/Modules/Analytics/Presentation/routes.php`
  - `app/Modules/Analytics/AnalyticsServiceProvider.php`
  - `bootstrap/providers.php`
  - `tests/Feature/Analytics/AnalyticsEndpointsTest.php`
- **Шаги:**
  1. Создать Use Cases `GetRevenueReport` и `GetOverviewAnalytics`, читающие данные только через `SalesAnalyticsQueryInterface` и `ClientsAnalyticsQueryInterface`.
  2. Реализовать `AnalyticsPeriodRequest`, контроллеры и маршруты с проверкой роли владельца (`isOwner` → иначе `403 FORBIDDEN`) и активной подписки (`company.active_subscription`).
  3. Зарегистрировать `AnalyticsServiceProvider` в `bootstrap/providers.php`.
  4. Написать Feature-тесты `AnalyticsEndpointsTest` (отчёт владельцу, отказ 403 администратору — критерий 12.7, изоляция компаний — критерий 12.6).
- **Критерий готовности:** все проверки (`lint`, `analyse`, `deps`, `test`) зелёные; этап 1 полностью завершён.

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
