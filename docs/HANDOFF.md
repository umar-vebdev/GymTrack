# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.

**Обновлено:** 2026-11-04
**Этап:** 3 — Каталог (витрины залов, модерация, публичный каталог, карта)
**Ветка:** main

## Текущее состояние

- Этапы 0–2 завершены. Эпик **E3-A** раскрыт и подтверждён разработчиком (T3-01…T3-07 в `docs/TASKS.md`).
- **T3-00** (`[x]`): проект отформатирован Pint, `composer lint` чистый.
- **T3-01** (`[x]`): миграция `companies_gym_profiles` (с `latitude`/`longitude`), enum `ModerationStatus`, сущность `GymProfile` (draft→pending→approved/rejected, `update` сбрасывает в draft), `InvalidGymProfileTransition`.
- **T3-02** (`[x]`): `GymProfileRepositoryInterface` (`findByGymId`, `save` = upsert), `EloquentGymProfileRepository`, `GymProfileModel`, биндинг в `CompaniesServiceProvider`.
- **T3-03** (`[x]`): `UpdateGymProfile` + `UpdateGymProfileCommand`, `PUT /api/v1/business/gyms/{gym}/profile` (только владелец, чужой зал → 404, `pending` → 400, координаты только парой), `GymProfileResource`, `GymNotFound`, `UpdateGymProfileTest` (7). Тесты: 295 зелёные, lint/analyse/deps чистые.
- **T3-04** (`[x]`): `SubmitGymProfileForModeration`, `POST /api/v1/business/gyms/{gym}/profile/submit` (только владелец, чужой зал → 404, неполная/повтор → 400), `SubmitGymProfileTest` (6). Тесты: 301 зелёные, lint/analyse/deps чистые.
- **T3-05a** (`[x]`): Use Case `ModerateGymProfile`, аудируемые события `GymProfileApproved` / `GymProfileRejected` в `Companies/Contracts`, `POST /api/v1/platform/gyms/{gym}/moderate` (`is_platform_admin`, approve/reject, reason при reject), `ModerateGymProfileTest` (8). Тесты: 309 зелёные, lint/analyse/deps чистые.

## Точный следующий шаг

1. **T3-05b**: Очередь витрин для платформы: метод `findByStatus` с пагинацией в `GymProfileRepositoryInterface` + `EloquentGymProfileRepository`, Use Case / Query `ListGymProfilesForModeration`, эндпоинт `GET /api/v1/platform/gym-profiles?status=pending` (доступен только админу платформы).
2. Потом T3-06 (фото зала).

## Окружение: важное про Docker и Git

- Из песочницы агента сокет Docker не виден и `.git` открыт только на чтение: `docker compose ...` и `git commit` запускать с `BypassSandbox: true`.

## Команды

| Действие | Команда |
|---|---|
| Запуск окружения | `docker compose up -d` |
| Стиль / анализ / границы | `composer lint` / `composer analyse` / `composer deps` |
| Тесты | `docker compose exec -T app php artisan test --compact` |

## Важные факты

- Платежей нет, только `PaymentMark`.
- У одного аккаунта (`users.id`) может быть несколько карточек клиента в разных залах.
- Каталог публичный (`/api/v1/public/...`), модерация через `/api/v1/platform/...`.
- Витрина — таблица `companies_gym_profiles` (1:1 с `gyms`), в модуле Companies.
