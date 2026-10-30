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

---

## Этап 2 — Client (приложение клиента)

1. [~] **E2-A Регистрация клиента и связывание с карточками зала** (Бизнес-ТЗ 5.1, 10, 12.9; Тех. ТЗ 7.1, 8, 9.3)

- [x] **T2-01** Поля профиля клиента (`birth_date`, `personal_data_consented_at`), метод обезличивания `User::anonymize()` и доменное правило `ClientRegistrationPolicy` (18+ и согласие)
- [x] **T2-02** Эндпоинт регистрации клиента (`POST /api/v1/client/auth/register`), отправка OTP и автосвязывание карточек залов после подтверждения номера (критерий 12.9)

### T2-03 Профиль клиента и удаление/обезличивание аккаунта по запросу
- **Статус:** `[~]`
- **Цель:** реализовать просмотр собственного профиля клиента (`GET /api/v1/client/profile`) и удаление/обезличивание аккаунта по запросу (`DELETE /api/v1/client/profile`) с отзывом токенов и сохранением истории в залах (Бизнес-ТЗ 10, Тех. ТЗ 8).
- **ТЗ:** Бизнес-ТЗ 2, 10; Тех. ТЗ 7.1, 8.
- **Файлы:**
  - `app/Modules/Identity/Application/DeleteClientAccount.php`
  - `app/Modules/Identity/Presentation/GetClientProfileController.php`
  - `app/Modules/Identity/Presentation/DeleteClientAccountController.php`
  - `app/Modules/Identity/Presentation/routes.php`
  - `tests/Feature/Identity/ClientProfileAndDeletionTest.php`
- **Шаги:**
  1. Реализовать Use Case `DeleteClientAccount` (обезличивание `User::anonymize()` и отзыв всех токенов Sanctum пользователя).
  2. Добавить контроллеры и маршруты `GET /api/v1/client/profile` и `DELETE /api/v1/client/profile`.
  3. Написать Feature-тест `ClientProfileAndDeletionTest`.
- **Критерий готовности:** все проверки (`lint`, `analyse`, `deps`, `test`) зелёные.
2. [ ] **E2-B** Visits: динамический QR, отметка посещения, правила `CheckInRule`, идемпотентность
3. [ ] **E2-C** Заявки на абонемент (Sales) и их автоотмена
4. [ ] **E2-D** Notifications: push и SMS, напоминания об окончании абонемента
5. [ ] **E2-E** API для приложения клиента: мои абонементы, история

## Этапы 3–5 (не раскрывать до завершения предыдущего этапа)
- Этап 3: публичный каталог, модерация, поиск, карта
- Этап 4: расписание и тренеры
- Этап 5: дневник тренировок
