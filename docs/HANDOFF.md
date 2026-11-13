# Handoff — GymTrack

**Обновлено:** 2026-11-13
**Текущий этап:** Этап 6 (`E6-B` — Вариант A: релизная готовность и пробелы ТЗ)
**Текущая задача:** `T6-10` — Наблюдаемость и эксплуатация (JSON-логирование и Docker worker/scheduler)

## Что сделано в последней сессии

- **`T6-01`–`T6-05b` (`E6-A` завершён)**: `gyms.timezone` (`D-007`), тренерский доступ в `EnsureActiveCompanySubscription` (`D-012`), Use Cases и HTTP API филиалов (`/api/v1/business/gyms`) и сотрудников (`/api/v1/business/staff`, только `owner`), `docs/api/companies.yaml`, аудит-логи и тесты (`528 passed, 3540 assertions`).
- **`T6-06` (сидеры начальных данных)**: добавлены идемпотентные `PlatformPlanSeeder` (5 тарифов платформы в TJS), `WorkoutsSeeder` (14 упражнений всех групп мышц и 3 готовых плана для библиотеки), обновлён `DatabaseSeeder`, добавлен шаг `php artisan db:seed` в `README.md` и тест `DatabaseSeederTest` (`529 passed, 3556 assertions`, `lint`/`analyse`/`deps` зелёные).
- **`T6-07` (локализация `ru`/`tg` и `Accept-Language`)**: добавлены `SetLocaleFromHeader` middleware (поддержка `ru`/`tg`, fallback `ru`, заголовок `Content-Language`), `lang/{ru,tg}/{api,validation}.php`, локализованные ответы исключений в `bootstrap/app.php` (включая `FORBIDDEN`/`TOO_MANY_REQUESTS`) и `EnsureActiveCompanySubscription`, обновлены ожидания `ru`-валидации в `ApiErrorFormatTest` и `RegisterUserTest`, добавлен `AcceptLanguageMiddlewareTest` (`531 passed, 3585 assertions`, `lint`/`analyse`/`deps` зелёные).
- **`T6-08` (rate limiting auth/OTP)**: настроены лимитеры `auth-login`, `auth-otp-send`, `auth-otp-verify`, `auth-register` (по телефону и IP) в `AppServiceProvider`, подключены в `routes/api.php` и `Identity/routes.php`, добавлен проброс HTTP-заголовков исключений (`Retry-After`, `X-RateLimit-*`) в `bootstrap/app.php`, написан `AuthRateLimitingTest` (`534 passed, 3623 assertions`, `lint`/`analyse`/`deps` зелёные).
- **`T6-09a`–`T6-09c` (OpenAPI-спецификации Этапов 1–2)**: добавлены [identity-auth.yaml](docs/api/identity-auth.yaml), [business-core.yaml](docs/api/business-core.yaml), [platform.yaml](docs/api/platform.yaml), [visits-analytics.yaml](docs/api/visits-analytics.yaml) и обновлены ссылки в `README.md`; все YAML провалидированы.

## Точный следующий шаг

1. Выполнить `T6-10` — добавить `RequestContextMiddleware` (`request_id`/`user_id`/`company_id` в `Log::withContext`), JSON-форматтер в `config/logging.php`, сервисы `worker` и `scheduler` в `docker-compose.yml`, обновить `README.md` и написать feature-тесты.
2. После завершения `E6-B` — раскрыть и согласовать план эпика `E6-C` (Вариант C).

## Команды проверки

```bash
composer lint
composer analyse
composer deps
docker compose exec -T app composer test -- --compact --colors=never
```

## Известные проблемы и замечания

- В feature-тестах Laravel кэширует контроллер на экземпляре `Route` в пределах одного теста, поэтому тестовый `Clock` с изменением времени внутри одного теста должен читать время из мутабельного свойства теста.
- В контейнере `app` команда `composer test` выполняется при условии `php artisan config:clear` перед прогоном; запуск через `docker compose exec -T app composer test -- --compact --colors=never`.
- Файл `.deptrac.cache` отслеживается в git и обновляется при запуске `composer deps`.
- Пробелы относительно ТЗ на 2026-11-13 (в работе в `E6-B`): JSON-формат логов и отдельные сервисы `worker`/`scheduler` в `docker-compose.yml` (`T6-10`).

## Нерешённые вопросы

- План `E6-B` подтверждён (`T6-06`–`T6-09c` выполнены, осталась `T6-10`, затем `E6-C`).
