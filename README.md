# GymTrack

**GymTrack** — облачная платформа (SaaS) для автоматизации фитнес-клубов и мобильное приложение для клиентов на едином бэкенде (Laravel 13, PHP 8.4, PostgreSQL 16, Redis 7).

- **Кабинет зала (Business):** учёт клиентов (CRM), типы и выдача абонементов, касса и товары, динамический HMAC QR для входа, ручная отметка и отмена посещений, витрина зала и фотографии, тренеры и расписание групповых занятий, аналитика выручки и посещаемости.
- **Приложение клиента (Client):** регистрация (18+ и согласие на ПДн), профиль и обезличивание, отметка посещения по динамическому QR, заявки на абонементы, push/SMS-уведомления, просмотр абонементов и истории по всем залам, запись на групповые занятия, дневник тренировок (библиотека, личные планы, тренировочные сессии и прогресс).
- **Публичный каталог (Public):** поиск залов с фильтрацией по названию, району, удобствам, ценам и геолокации (радиус/область карты), просмотр карточки и расписания зала.
- **Платформа (Platform):** управление подписками залов, модерация витрин каталога, администрирование справочника упражнений и библиотеки готовых тренировочных планов.

> **Важное бизнес-правило:** в системе **нет онлайн-платежей**. Проводятся только отметки о приёме оплаты сотрудником (`PaymentMark`: наличные, терминал или перевод) с фиксацией ответственного лица и времени.

---

## Текущий статус разработки

- [x] **Этап 0:** Базовый каркас, Docker Compose, CI, архитектурные тесты, Shared Kernel.
- [x] **Этап 1 (Business):** Модули `Identity`, `Companies`, `Audit`, `Clients`, `Memberships`, `Sales`, `Billing`, `Analytics`.
- [x] **Этап 2 (Client):** Регистрация клиента (18+ и ПДн), динамический HMAC QR и отметка визитов (`Visits`), заявки на абонементы (`Sales`), уведомления (`Notifications`), справочник валют компании, клиентские эндпоинты абонементов и истории.
- [x] **Этап 3 (Каталог и модерация):** Витрины и фото залов (`Companies`), модерация платформой, явная публикация, ULID (`public_id`), публичный каталог (`Catalog`) с поиском, фильтрами и геопоиском.
- [x] **Этап 4 (Расписание и тренеры):** Роль тренера и профили тренеров (`Trainers`), шаблоны и сессии групповых занятий (`Schedule`), запись/отмена с `Idempotency-Key` и списанием/возвратом посещений абонемента (`Memberships`), публичное расписание в каталоге (`Catalog`).
- [x] **Этап 5 (Дневник тренировок):** Модуль `Workouts` — системные и личные упражнения, библиотека готовых планов платформы, собственные планы клиента и копирование из библиотеки, тренировочные сессии с фиксацией подходов и история прогресса по упражнениям.

Покрытие и качество: **511 тестов (3424 проверок)**, статический анализ (**Larastan**, уровень 8+), контроль границ модулей (**Deptrac**, 0 нарушений), стиль кода (**Laravel Pint**).

---

## Архитектура

Проект построен как **модульный монолит** с соблюдением принципов чистой архитектуры, ООП и SOLID.

```text
app/
├── Modules/                  # 14 изолированных функциональных модулей
│   ├── Analytics/            # Сводные отчёты по выручке и посещаемости (только чтение)
│   ├── Audit/                # Неизменяемый журнал аудита доменных событий
│   ├── Billing/              # Тарифы платформы, пробный период (14 дней), подписки компаний
│   ├── Catalog/              # Публичный каталог залов, геопоиск, публичное расписание
│   ├── Clients/              # CRM клиентов, связывание по PhoneVerified, слияние дубликатов
│   ├── Companies/            # Компании, залы, валюты, сотрудники (RBAC), витрины, фото, модерация
│   ├── Identity/             # Аутентификация, SMS OTP, Sanctum токены, профиль и удаление ПДн
│   ├── Memberships/          # Типы абонементов, правила и стратегии списания, заморозка, отмена
│   ├── Notifications/        # Журнал уведомлений, Push/SMS шлюзы, очереди, напоминания
│   ├── Sales/                # Товары, касса (PaymentMark), возвраты, заявки на абонементы
│   ├── Schedule/             # Шаблоны и слоты групповых занятий, запись и отмена броней
│   ├── Trainers/             # Профили тренеров залов и публичные сводки для каталога
│   ├── Visits/               # Динамический HMAC QR, чекин по QR, ручные визиты, отмена
│   └── Workouts/             # Справочник упражнений, библиотека и личные планы, сессии и прогресс
└── Shared/                   # Shared Kernel: Money, CompanyId, Clock, DomainEvent, CompanyScope
```

### Структура каждого модуля

```text
НазваниеМодуля/
├── Contracts/       # Публичные DTO, интерфейсы и события для взаимодействия с другими модулями
├── Domain/          # Чистый PHP: сущности, Value Objects, правила, исключения (без Laravel/Eloquent)
├── Application/     # Use Cases (один класс на сценарий), команды, запросы, обработчики событий
├── Infrastructure/  # Eloquent-модели, репозитории, миграции, адаптеры шлюзов
└── Presentation/    # HTTP-контроллеры, FormRequests, Resources, Policies, консольные команды, routes.php
```

### Ключевые архитектурные правила

1. **Изоляция модулей:** модули взаимодействуют **только** через интерфейсы/DTO из `Contracts` и доменные события. Прямые связи Eloquent и `JOIN` через границы модулей запрещены и проверяются через Deptrac.
2. **Чистый Domain:** доменный слой не зависит от Laravel, БД и сторонних пакетов (подтверждается архитектурными тестами Pest).
3. **Мультиарендность (Multi-tenancy):** данные залов изолированы по `company_id` с помощью `CompanyScope`, составных индексов БД и проверок принадлежности в `Policy`.
4. **Финансы, вес и время:** деньги хранятся целыми числами в минимальных единицах (`Money`), вес в тренировках — целыми граммами, время в БД — исключительно в UTC.
5. **Надёжность и идемпотентность:** критичные операции (чекин, продажа, заявка, запись на занятие) поддерживают заголовок `Idempotency-Key`, выполняются в транзакциях БД с блокировкой строк (`lockForUpdate`).
6. **Динамический QR:** HMAC-SHA256 токен с 60-секундным интервалом (принимается текущий и предыдущий слот) и зашифрованным секретом зала.

---

## Структура API (`/api/v1`)

Все ответы и ошибки API стандартизированы (`{"data": ...}` или `{"error": {"code": "...", "message": "...", "details": ...}}`).

### Общие маршруты (`/api/v1`)
- `GET /health` — проверка состояния приложения, PostgreSQL и Redis.
- `POST /auth/register`, `POST /auth/login`, `POST /auth/send-otp`, `POST /auth/verify-phone` — регистрация, вход и подтверждение телефона.
- `GET /user` — текущий авторизованный пользователь.

### Кабинет зала (`/api/v1/business/...`, Sanctum)
- **Компания, залы, сотрудники и витрина:** `POST /companies`, `GET /currencies`, `PATCH /company/currency`, `GET/POST /gyms`, `PATCH /gyms/{gym}`, `GET/POST /staff`, `PATCH/DELETE /staff/{staff}`, `PUT /gyms/{gym}/profile`, `POST /gyms/{gym}/profile/submit`, `PATCH /gyms/{gym}/publication`, `POST/DELETE /gyms/{gym}/photos/{photo?}`.
- **Клиенты (CRM):** `GET/POST /clients`, `POST /clients/merge`.
- **Абонементы:** `GET/POST /membership-types`, `PATCH /membership-types/{id}/active`, `GET /clients/{id}/memberships`, `GET /memberships/expiring`, `POST /memberships/{id}/{freeze,unfreeze,cancel}`.
- **Товары, касса и заявки:** `GET/POST /products`, `PATCH /products/{id}`, `GET/POST /sales`, `POST /sales/{id}/refund`, `GET /clients/{id}/sales`, `GET /membership-requests`, `POST /membership-requests/{id}/{pay,cancel}`.
- **Посещения и QR:** `GET /gyms/{id}/qr`, `GET /visits`, `GET/POST /clients/{id}/visits`, `POST /visits/{id}/cancel`.
- **Тренеры и расписание:** `GET/POST /trainers`, `GET/PATCH /trainers/{trainer}`, `GET/POST /schedule/templates`, `PATCH /schedule/templates/{template}`, `GET /schedule/sessions`, `POST /schedule/sessions/generate`, `POST /schedule/sessions/{session}/cancel`, `GET /schedule/sessions/{session}/bookings`.
- **Биллинг и аналитика:** `GET /billing/plans`, `GET /billing/subscription`, `GET /analytics/revenue`, `GET /analytics/overview`.

### Приложение клиента (`/api/v1/client/...`, Sanctum)
- **Профиль и уведомления:** `POST /auth/register`, `GET/DELETE /profile`, `POST/DELETE /device-tokens`.
- **Абонементы, покупки и посещения:** `POST /visits/check-in`, `GET /visits`, `GET/POST /membership-requests`, `GET /memberships`, `GET /purchases`.
- **Расписание и запись:** `GET /gyms/{gym}/schedule`, `POST /schedule/sessions/{session}/book`, `POST /schedule/bookings/{booking}/cancel`, `GET /schedule/bookings`.
- **Дневник тренировок (`/workouts`):**
  - упражнения: `GET/POST /exercises`, `PATCH /exercises/{exercise}`, `GET /exercises/{exercise}/progress`;
  - библиотека и планы: `GET /library`, `GET /library/{plan}`, `POST /library/{plan}/copy`, `GET/POST /plans`, `GET/PATCH/DELETE /plans/{plan}`;
  - сессии: `GET/POST /sessions`, `GET /sessions/{session}`, `POST /sessions/{session}/sets`, `POST /sessions/{session}/complete`.

### Публичный каталог (`/api/v1/public/...`, без авторизации)
- `GET /gyms` — поиск и фильтрация опубликованных залов (`q`, `district`, `amenities[]`, `price_min`/`price_max` + `currency`, геофильтры `lat`/`lng`/`radius_km` и `sw_lat`/`sw_lng`/`ne_lat`/`ne_lng`).
- `GET /gyms/{publicId}` — карточка зала с актуальными тарифами абонементов.
- `GET /gyms/{publicId}/schedule` — открытое расписание предстоящих групповых занятий зала.

### Администрирование платформы (`/api/v1/platform/...`, `is_platform_admin`)
- `POST /companies/{id}/subscription/activate` — активация подписки компании.
- `GET /gym-profiles`, `POST /gyms/{gym}/moderate` — очередь и модерация витрин залов.
- `GET/POST /workouts/exercises`, `PATCH /workouts/exercises/{exercise}`, `GET/POST /workouts/plans`, `GET/PATCH /workouts/plans/{plan}`.

### OpenAPI-спецификации (`docs/api/`)
- [companies.yaml](docs/api/companies.yaml) — управление филиалами и сотрудниками компании
- [gym-profiles.yaml](docs/api/gym-profiles.yaml) — редактирование и публикация витрин
- [gym-photos.yaml](docs/api/gym-photos.yaml) — загрузка и удаление фотографий зала
- [public-catalog.yaml](docs/api/public-catalog.yaml) — публичный каталог, геопоиск и расписание зала
- [trainers.yaml](docs/api/trainers.yaml) — управление тренерами зала
- [schedule.yaml](docs/api/schedule.yaml) — шаблоны, сессии расписания и запись клиентов
- [workouts.yaml](docs/api/workouts.yaml) — дневник тренировок (упражнения, библиотека, планы, сессии, прогресс)

---

## Запуск проекта локально (Docker Compose)

### Требования
- Docker Engine 24+
- Docker Compose v2+

### Быстрый старт

1. Склонируйте репозиторий и создайте файл окружения:
   ```bash
   cp .env.example .env
   ```

2. Запустите контейнеры (`app` PHP 8.4-FPM, `web` Nginx, `db` PostgreSQL 16, `redis` Redis 7):
   ```bash
   docker compose up -d
   ```

3. Установите зависимости:
   ```bash
   docker compose exec app composer install
   ```

4. Сгенерируйте ключ приложения:
   ```bash
   docker compose exec app php artisan key:generate
   ```

5. Примените миграции и заполните базовые справочники (идемпотентные сидеры тарифов платформы и библиотеки тренировок):
   ```bash
   docker compose exec app php artisan migrate
   docker compose exec app php artisan db:seed
   ```

Приложение доступно по адресу: **http://localhost:8000**  
Health-check эндпоинт: **http://localhost:8000/api/v1/health**

---

## Контроль качества кода

Все проверки должны проходить перед каждым коммитом (конфигурация CI: [.github/workflows/ci.yml](.github/workflows/ci.yml)):

```bash
# 1. Проверка и автоматическое исправление стиля кода (Laravel Pint)
composer lint
composer lint:fix

# 2. Статический анализ типов (Larastan / PHPStan, уровень 8)
composer analyse

# 3. Контроль архитектурных границ модулей и слоёв (Deptrac)
composer deps

# 4. Запуск тестов (Pest / PHPUnit в Docker с PostgreSQL)
docker compose exec -T app composer test -- --compact --colors=never
```

---

## Фоновые команды и расписание

Периодические команды настроены в [routes/console.php](routes/console.php):

| Команда | Расписание | Описание |
|---|---|---|
| `memberships:expire-due` | Ежедневно | Завершение абонементов с истекшим сроком или исчерпанными занятиями |
| `billing:expire-due` | Ежедневно | Завершение истекших пробных периодов (14 дней) и подписок компаний |
| `sales:expire-requests` | Ежечасно | Автоматическая отмена неоплаченных клиентских заявок старше 24 часов |
| `notifications:send-expiring-reminders` | Ежедневно | Отправка push/SMS-напоминаний клиентам за 7 дней и за 1 день до окончания абонемента |

---

## Документация проекта

- [Бизнес-требования (ТЗ v2)](docs/specs/GymTrack_TZ_v2.md)
- [Техническое задание](docs/specs/GymTrack_Technical_TZ.md)
- [Чек-лист задач](docs/TASKS.md)
- [Журнал архитектурных решений](docs/DECISIONS.md)
- [Текущее состояние (Handoff)](docs/HANDOFF.md)
