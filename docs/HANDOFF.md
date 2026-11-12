# Handoff — GymTrack

**Обновлено:** 2026-11-12
**Текущий этап:** Этап 5 — Дневник тренировок (модуль `Workouts`; в работе эпик `E5-A`)
**Текущая задача:** `T5-03` `[ ]` — Домен и хранение тренировочных планов (`WorkoutPlan`, `WorkoutPlanItem`, миграции и репозиторий)

## Что сделано в последней сессии

- **T4-12c** (`eda5e92`): Добавлен `membership_id` в `ClassBookingResource`, проброшен `$e->errorCode` в контроллерах, обновлён `docs/api/schedule.yaml` (`479 passed, 2965 assertions`).
- **D-015 + E4-E (`T4-13`…`T4-15b`)** (`94cc375`, `77c21a2`, `e6e2db4`, `1cb04cc`): Полностью завершён Этап 4 — добавлено публичное расписание залов в каталоге (`GET /api/v1/public/gyms/{publicId}/schedule`, контракты `PublicTrainerQueryInterface` и `ScheduleCatalogQueryInterface`, OpenAPI `docs/api/public-catalog.yaml`; `487 passed, 3097 assertions`).
- **D-016 + T5-01** (`6abd0c0`): Утверждён объём Этапа 5 (`E5-A`…`E5-C`, модуль `Workouts`) и план `E5-A`; зарегистрирован `WorkoutsServiceProvider`, добавлена миграция `workouts_exercises`, домен `Exercise` / `MuscleGroup` / `InvalidExercise`, `EloquentExerciseRepository` и тесты (`11 passed, 145 assertions`).
- **T5-02a** (`f50f4a0`): Реализованы Use Cases `CreateExercise`, `UpdateExercise`, `ListExercises` (`WorkoutResourceNotFound`) и `ExerciseUseCasesTest` (`1 passed, 12 assertions`).
- **T5-02b** (`cd9fa0c`): Реализованы платформенные эндпоинты `/api/v1/platform/workouts/exercises[/{exercise}]` (`PlatformExercisesController`, `FormRequest`, `ExerciseResource`, `routes.php`) и `ExercisesEndpointsTest` (`2 passed, 31 assertions`).
- **T5-02c:** Добавлен `ClientExercisesController` и маршруты `/api/v1/client/workouts/exercises[/{exercise}]` с тестами изоляции в `ExercisesEndpointsTest` (`3 passed, 53 assertions`, `lint`/`analyse`/`deps` зелёные).

## Точный следующий шаг

1. Выполнить **T5-03**: создать миграции `workouts_plans` и `workouts_plan_items`, доменные сущности `WorkoutPlan`, `WorkoutPlanItem`, `PlanLevel`, `InvalidWorkoutPlan`, `WorkoutPlanRepositoryInterface` + `EloquentWorkoutPlanRepository` и тесты.
2. Далее выполнить `T5-04` (библиотека готовых планов платформы + OpenAPI `docs/api/workouts.yaml`) для завершения эпика `E5-A`.

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
