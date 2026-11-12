# Handoff — GymTrack

**Обновлено:** 2026-11-12
**Текущий этап:** Этап 5 — Дневник тренировок (модуль `Workouts`; в работе эпик `E5-B`)
**Текущая задача:** `T5-05b` `[~]` — HTTP-эндпоинты личных планов клиента и OpenAPI (`ClientWorkoutPlansController`, `docs/api/workouts.yaml`, `ClientPlansEndpointsTest`)

## Что сделано в последней сессии

- **D-016 + E5-A (`T5-01`…`T5-04b`)** (`6abd0c0`…`bc94c8e`): Полностью завершён эпик `E5-A` — модуль `Workouts`, упражнения платформы и клиента, готовые планы платформы и публичная для клиентов библиотека (`/api/v1/client/workouts/library`, OpenAPI `docs/api/workouts.yaml`; `503 passed, 3284 assertions`).
- **D-017 + T5-05a:** Подтверждён план `E5-B`; добавлен `resolveClientItems()` в `WorkoutPlanAssembler`, реализованы `CreateClientPlan`, `UpdateClientPlan`, `ArchiveClientPlan`, `ListClientPlans`, `GetClientPlan`, `CopyLibraryPlanToClient` и тесты `ClientPlanUseCasesTest` (2 теста с `LibraryPlanUseCasesTest`, `26 assertions`; `lint`/`analyse`/`deps` зелёные).

## Точный следующий шаг

1. Выполнить **T5-05b**: создать `ClientWorkoutPlansController`, `StoreClientPlanRequest`, `UpdateClientPlanRequest` (опционально `CopyLibraryPlanRequest`), подключить маршруты в `routes.php`, дополнить `docs/api/workouts.yaml` и написать `ClientPlansEndpointsTest`.
2. После завершения `E5-B` раскрыть эпик **E5-C** (тренировочные сессии и прогресс) в `docs/TASKS.md` и запросить подтверждение разработчика.

## Команды проверки

```bash
composer lint
composer analyse
composer deps
docker compose exec -T app composer test
```

## Известные проблемы и замечания

- В feature-тестах Laravel кэширует контроллер на экземпляре `Route` в пределах одного теста, поэтому тестовый `Clock` с изменением времени внутри одного теста должен читать время из мутабельного свойства теста.
- В контейнере `app` команда `composer test` выполняется при условии `php artisan config:clear` перед прогоном; запуск через `docker compose exec -T app composer test -- --compact --colors=never`.
- Коммиты на локальной ветке `main` впереди `origin/main` (пуш не выполнялся, только по запросу).

## Нерешённые вопросы

- Нет.
