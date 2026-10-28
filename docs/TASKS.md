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

---

## Этап 1 — Business (Sales, Billing, Analytics)

6. **E1-F Sales**: товары, касса, `PaymentMark`, ручная выдача абонемента
   > Раскрытие эпика подтверждено разработчиком 2026-10-28.
   > **ВАЖНО (требование разработчика):** выдача абонемента выполняется в одной транзакции с отметкой оплаты (`PaymentMark`), а возврат продажи владельцем отменяет выданный абонемент и возвращает остаток товаров с записью в `Audit`. Ссылки: Бизнес-ТЗ 3, 4.3, 4.4, 4.5; Тех. ТЗ 3.6–3.8, 6, 7.3, 8, 9.2.

   - [x] **T1-29** Домен и инфраструктура товаров (`Product`): сущность `Product` (название, цена `Money`, опциональный остаток `stock_quantity` с `CHECK >= 0`, активность `is_active`, методы изменения остатка и активности), миграция `sales_products`, `ProductRepositoryInterface`, `EloquentProductRepository`, `SalesServiceProvider` (+ регистрация в `bootstrap/providers.php`), тест репозитория.
     - Заметки: таблица `sales_products` (префикс модуля по Тех. ТЗ 6.1), ограничения целостности `CHECK (price_minor >= 0)` и `CHECK (stock_quantity IS NULL OR stock_quantity >= 0)`. `stock_quantity = null` означает, что учёт остатков не ведётся.

   - [x] **T1-30a** Управление товарами (Domain + Application): исключение `ProductNotFound`, сценарии `CreateProduct` (валюта по умолчанию `TJS` согласно D-003), `ListProducts` (фильтр `onlyActive`), `UpdateProduct` (обновление `is_active` и/или `stock_quantity` внутри транзакции под `findByIdForUpdate`).
     - Заметки: `UpdateProduct` разделяет флаг `updateStock` и значение `?int $stockQuantity`, чтобы можно было сбросить учёт остатка в `null`.

   - [x] **T1-30b** Управление товарами (HTTP API + Feature-тесты): валидация (`CreateProductRequest`, `UpdateProductRequest`), контроллеры и маршруты `POST /api/v1/business/products`, `GET /api/v1/business/products`, `PATCH /api/v1/business/products/{id}` + `ProductsManagementTest`.
     - Заметки: создание и редактирование товаров доступны только владельцу компании; список товаров с фильтром `?only_active=` доступен владельцу и администратору.

   - [x] **T1-31a** Домен продаж и отметок оплаты: перечисления `PaymentMethod` (`cash` | `card_terminal` | `transfer`) и `SaleStatus` (`completed` | `refunded`), Value Object `PaymentMark` (способ, сумма `Money`, кто принял, когда) и `SaleItem` (товар или абонемент с проверкой инвариантов), сущность `Sale` (проверка совпадения суммы `PaymentMark` с суммой позиций, проверка обязательности `clientId` при наличии абонемента, метод `refund()`), unit-тесты.
     - Заметки: для продажи только товаров `clientId` может быть `null`, при наличии позиции абонемента `clientId` обязателен (Бизнес-ТЗ 4.5).

   - [x] **T1-31b** Инфраструктура продаж: миграция `sales_sales` и `sales_sale_items` (с `CHECK >= 0`, финансовые записи не удаляются — Тех. ТЗ 6.1), `SaleModel`, `SaleItemModel`, `SaleRepositoryInterface`, `EloquentSaleRepository` (+ привязка в `SalesServiceProvider`), тест репозитория.
     - Заметки: составные индексы `(company_id, created_at)` и `(company_id, client_id)` согласно Тех. ТЗ 6.3. Связь `SaleModel::items()` находится строго внутри модуля `Sales`.

   - [x] **T1-32** Касса (Use Case `RegisterSale`): продажа товаров и ручная выдача абонемента с `PaymentMark` в одной транзакции (проверка клиента, списание остатка товаров под `lockForUpdate`, вызов `MembershipIssuerInterface::issue()`, сохранение `Sale` + `PaymentMark`, аудируемое событие `PaymentMarked`).
     - Заметки: в `MembershipIssuerInterface` добавлен метод `quoteType(int $companyId, int $typeId): MembershipTypeQuote`, чтобы модуль `Sales` получал название и цену типа абонемента строго через `Contracts`. Вся операция выполняется в одной `DB::transaction`, событие `PaymentMarked` (`AuditableEvent`) отправляется после фиксации.

   - [x] **T1-33** Идемпотентность (`Idempotency-Key`) и HTTP-эндпоинт кассы `POST /api/v1/business/sales`: хранение ключа идемпотентности (Тех. ТЗ 7.3), контроллер, валидация, Feature-тесты (продажа товара без клиента, продажа абонемента + товара клиенту в одной транзакции, идемпотентность, нехватка остатка).
     - Заметки: в `sales_sales` добавлен столбец `idempotency_key` (`UNIQUE (company_id, idempotency_key)`). При повторном запросе с тем же `Idempotency-Key` возвращается ранее созданная продажа без повторного списания остатков, выдачи абонемента и отправки `PaymentMarked`.

   - [ ] **T1-34** История покупок клиента и список продаж зала: `GET /api/v1/business/clients/{id}/sales` (Бизнес-ТЗ 4.4) и `GET /api/v1/business/sales` + Feature-тесты.
     - Ссылки: Бизнес-ТЗ 4.4, 4.5; Тех. ТЗ 7.1, 7.2, 8.
     - Файлы: `Application/ListCompanySales.php`, `Application/ListClientSales.php`, `Presentation/ListCompanySalesController.php`, `Presentation/ListClientSalesController.php`, `Presentation/routes.php`, `tests/Feature/Sales/ListSalesTest.php`.
     - Готово когда: Feature-тесты проверяют получение списка продаж зала и истории покупок клиента владельцем и администратором, а также изоляцию компаний.

   - [ ] **T1-35** Отмена/возврат продажи владельцем со связкой с `Memberships`: контракт `MembershipCancellerInterface` в `Memberships/Contracts`, Use Case `RefundSale` и эндпоинт `POST /api/v1/business/sales/{id}/refund` (только владелец, обязательная причина, возврат остатка товаров и отмена выданного абонемента в одной транзакции, событие `SaleRefunded` → `Audit`) + Feature-тесты.

7. [ ] **E1-G Billing**: тарифы платформы, пробный период 14 дней, режим «только чтение» после окончания
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
