# Handoff — GymTrack

**Обновлено:** 2026-11-13
**Текущий этап:** Этап 6 (`E6-B` — Вариант A: релизная готовность и пробелы ТЗ)
**Текущая задача:** `T6-08` — Rate limiting для аутентификации и OTP

## Что сделано в последней сессии

- **`T6-01`–`T6-05b` (`E6-A` завершён)**: `gyms.timezone` (`D-007`), тренерский доступ в `EnsureActiveCompanySubscription` (`D-012`), Use Cases и HTTP API филиалов (`/api/v1/business/gyms`) и сотрудников (`/api/v1/business/staff`, только `owner`), `docs/api/companies.yaml`, аудит-логи и тесты (`528 passed, 3540 assertions`).
- **`T6-06` (сидеры начальных данных)**: добавлены идемпотентные `PlatformPlanSeeder` (5 тарифов платформы в TJS), `WorkoutsSeeder` (14 упражнений всех групп мышц и 3 готовых плана для библиотеки), обновлён `DatabaseSeeder`, добавлен шаг `php artisan db:seed` в `README.md` и тест `DatabaseSeederTest` (`529 passed, 3556 assertions`, `lint`/`analyse`/`deps` зелёные).
- **`T6-07` (локализация `ru`/`tg` и `Accept-Language`)**: добавлены `SetLocaleFromHeader` middleware (поддержка `ru`/`tg`, fallback `ru`, заголовок `Content-Language`), `lang/{ru,tg}/{api,validation}.php`, локализованные ответы исключений в `bootstrap/app.php` (включая `FORBIDDEN`/`TOO_MANY_REQUESTS`) и `EnsureActiveCompanySubscription`, обновлены ожидания `ru`-валидации в `ApiErrorFormatTest` и `RegisterUserTest`, добавлен `AcceptLanguageMiddlewareTest` (`531 passed, 3585 assertions`, `lint`/`analyse`/`deps` зелёные).

## Точный следующий шаг

1. Выполнить `T6-08` — настроить `RateLimiter` для `/auth/login`, `/auth/send-otp`, `/auth/verify-phone`, `/client/auth/register` и написать feature-тесты `429`.
2. Далее перейти к OpenAPI-спецификациям Этапов 1–2 (`T6-09a`, `T6-09b`, `T6-09c`).

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
- Пробелы относительно ТЗ на 2026-11-13 (в работе в `E6-B`): нет OpenAPI-спецификаций для Этапов 1–2, rate limiting настроен только на `/visits/*`, нет JSON-формата логов и отдельных сервисов `worker`/`scheduler` в `docker-compose.yml`.

## Нерешённые вопросы

- План `E6-B` подтверждён (`T6-06`, `T6-07` выполнены, далее `T6-08`…`T6-10`, затем `E6-C`).
