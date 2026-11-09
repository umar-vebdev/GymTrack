# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.

**Обновлено:** 2026-11-07
**Этап:** 4 — Расписание и тренеры (планирование; этап 3 принят)
**Ветка:** main

## Текущее состояние

- Этапы 0–2 завершены. Этап 3 (каталог): эпики **E3-A, E3-B, E3-C завершены** (`T3-01`…`T3-16`), ждёт приёмки разработчиком.

## Последняя сессия

- Этап 3 принят; принят объём этапа 4 (D-011) и доступ тренера (D-012).
- **T4-01 [x]**: `Role::TRAINER`, `companyIdForUser` только для owner/admin, `companyIdForTrainer`.
- **T4-02 [x]**: модуль `Trainers` — миграция `trainers_profiles`, сущность `Trainer`, `TrainerRepositoryInterface` + Eloquent-реализация, 7 тестов.
- **433 теста, 2257 проверок**; `composer lint`, `composer analyse`, `composer test`, `composer deps` проходят.

## Текущая задача и точный следующий шаг

1. **T4-03 [ ]** (`docs/TASKS.md`, E4-A): `CreateTrainer`/`UpdateTrainer` + `POST`/`PATCH /api/v1/business/trainers` (только владелец), OpenAPI. Проверка, что зал принадлежит компании, — через **новый контракт в `Companies/Contracts`** (например, `GymLookupInterface::belongsToCompany(gymId, companyId)`), а не через внутренние классы Companies. Если задача превысит ~5 файлов, разбить на T4-03a (контракт + `CreateTrainer`) и T4-03b (`UpdateTrainer` + эндпоинты). Ошибки: чужой зал → 404 `NOT_FOUND`, не владелец → 403, невалидный профиль → 422. Перед стартом посмотреть, как сделаны `UpdateGymProfileController` и `GymProfilePolicy`.
2. Привязка аккаунта тренера (`user_id`) в T4-03 не реализуется (в проекте нет добавления сотрудников); нужен отдельный вопрос разработчику.

## Ранее реализовано: фотографии

- `POST /api/v1/business/gyms/{gym}/photos`: jpeg/png/webp до 5 МБ, максимум 10; только владелец, pending запрещён, изменение → draft.
- `DELETE /api/v1/business/gyms/{gym}/photos/{photo}`: общий с загрузкой lock строки зала, проверка компании/зала/фото, сброс модерации; успех 200 с `data: null`.
- Удаление восстанавливает файл при перехваченном сбое; аварийное завершение процесса не покрывается. Отсутствующий файл не мешает удалить запись (D-009, допущение ждёт подтверждения).
- OpenAPI: `docs/api/gym-photos.yaml`, `docs/api/gym-profiles.yaml`, `docs/api/public-catalog.yaml`.

## Окружение: важное про Docker и Git

- Из песочницы сокет Docker недоступен, `.git` доступен только для чтения: Docker и запись git требуют `BypassSandbox: true`. PHPStan вне песочницы нужен локальный сокет рабочих процессов; использовать тот же режим.

## Команды

| Действие | Команда |
|---|---|
| Запуск окружения | `docker compose up -d` |
| Стиль / анализ / границы | `composer lint` / `composer analyse` / `composer deps` |
| Тесты | `docker compose exec -T app composer test -- --compact --colors=never` |

## Важные факты

- Платежей нет, только `PaymentMark`.
- У одного аккаунта (`users.id`) может быть несколько карточек клиента в разных залах.
- Каталог публичный (`/api/v1/public/...`), модерация через `/api/v1/platform/...`.
- Витрина — таблица `companies_gym_profiles` (1:1 с `gyms`), в модуле Companies.
