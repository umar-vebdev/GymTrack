# Handoff — GymTrack

**Обновлено:** 2026-11-13
**Текущий этап:** Этап 6 (`E6-B` — Вариант A: релизная готовность и пробелы ТЗ)
**Текущая задача:** `T6-07` — Локализация `ru`/`tg` и middleware `Accept-Language`

## Что сделано в последней сессии

- **`T6-01`–`T6-05b` (`E6-A` завершён)**: `gyms.timezone` (`D-007`), тренерский доступ в `EnsureActiveCompanySubscription` (`D-012`), Use Cases и HTTP API филиалов (`/api/v1/business/gyms`) и сотрудников (`/api/v1/business/staff`, только `owner`), `docs/api/companies.yaml`, аудит-логи и тесты (`528 passed, 3540 assertions`).
- **`T6-06` (сидеры начальных данных)**: добавлены идемпотентные `PlatformPlanSeeder` (5 тарифов платформы в TJS), `WorkoutsSeeder` (14 упражнений всех групп мышц и 3 готовых плана для библиотеки), обновлён `DatabaseSeeder`, добавлен шаг `php artisan db:seed` в `README.md` и тест `DatabaseSeederTest` (`529 passed, 3556 assertions`, `lint`/`analyse`/`deps` зелёные).

## Точный следующий шаг

1. Выполнить `T6-07` — добавить `lang/ru/`, `lang/tg/`, `SetLocaleFromHeader` middleware (`Accept-Language` -> `ru`/`tg`, fallback `ru`) и feature-тесты.
2. После этого перейти к `T6-08` (rate limiting для auth/OTP).

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
- Пробелы относительно ТЗ на 2026-11-13 (в работе в `E6-B`): отсутствует `lang/` (`ru`/`tg` + `Accept-Language`), нет OpenAPI-спецификаций для Этапов 1–2, rate limiting настроен только на `/visits/*`, нет JSON-формата логов и отдельных сервисов `worker`/`scheduler` в `docker-compose.yml`.

## Нерешённые вопросы

- План `E6-B` подтверждён (`T6-06` выполнен, далее `T6-07`…`T6-10`, затем `E6-C`).
