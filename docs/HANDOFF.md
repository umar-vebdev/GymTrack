# Handoff — GymTrack

**Обновлено:** 2026-11-13
**Текущий этап:** Этап 6 (`E6-B` завершён; впереди `E6-C` — Вариант C)
**Текущая задача:** Ожидается подтверждение детализированного плана эпика `E6-C`

## Что сделано в последней сессии (`E6-B` завершён)

- **`T6-06` (сидеры начальных данных):** идемпотентные `PlatformPlanSeeder` (5 тарифов в TJS) и `WorkoutsSeeder` (14 упражнений всех групп мышц и 3 плана тренировок), обновлены `DatabaseSeeder` и `README.md`, добавлен `DatabaseSeederTest`.
- **`T6-07` (локализация `ru`/`tg` и `Accept-Language`):** `SetLocaleFromHeader` (fallback `ru`, заголовок `Content-Language`), `lang/{ru,tg}/{api,validation}.php`, локализованные ответы исключений в `bootstrap/app.php` (`FORBIDDEN`, `TOO_MANY_REQUESTS`) и `EnsureActiveCompanySubscription`, `AcceptLanguageMiddlewareTest`.
- **`T6-08` (rate limiting auth/OTP):** лимитеры `auth-login`, `auth-otp-send`, `auth-otp-verify`, `auth-register` в `AppServiceProvider`, подключение в `routes/api.php` и `Identity/routes.php`, проброс HTTP-заголовков `429` в `bootstrap/app.php`, `AuthRateLimitingTest`.
- **`T6-09a`–`T6-09c` (OpenAPI-спецификации Этапов 1–2):** добавлены `identity-auth.yaml`, `business-core.yaml`, `platform.yaml`, `visits-analytics.yaml`, обновлены ссылки в `README.md`.
- **`T6-10` (наблюдаемость и эксплуатация):** `RequestContextMiddleware` (`X-Request-Id`, `request_id`/`user_id`/`company_id` в `Log::withContext`), каналы `json`/`json_daily`/`json_stderr` (`Monolog\Formatter\JsonFormatter`) в `config/logging.php`, `.env.example` (`LOG_STACK=json`), сервисы `worker` (`queue:work`) и `scheduler` (`schedule:work`) в `docker-compose.yml`, обновлён `README.md`, `RequestContextLoggingTest`.

## Актуальное состояние проверок

- `composer lint` (`pint --test`) — ✓
- `composer analyse` (`phpstan`, level 8) — ✓ (0 errors)
- `composer deps` (`deptrac`) — ✓ (0 violations)
- `docker compose config -q` — ✓
- `docker compose exec -T app composer test -- --compact --colors=never` — ✓ (`536 passed, 3657 assertions`)

## Точный следующий шаг

1. Согласовать с разработчиком задачи эпика `E6-C` (`Could`-функции: геопроверка при чекине, отзывы и рейтинг залов, личный QR клиента, объявления зала, HTTP-адаптеры SMS/Push).
2. После подтверждения приступить к первой задаче `E6-C` (`T6-11`).

## Нерешённые вопросы

- План `E6-C` требует подтверждения разработчика (в том числе выбор параметров HTTP-адаптеров SMS/Push или конфигурационных драйверов и объёма `E6-C`).
