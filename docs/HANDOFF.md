# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.

**Обновлено:** 2026-11-03
**Этап:** 3 — Каталог (витрины залов, модерация, публичный каталог, карта)
**Ветка:** main

## Текущее состояние

- Этапы 0–2 завершены. Эпик **E3-A** раскрыт и подтверждён разработчиком (T3-01…T3-07 в `docs/TASKS.md`).
- **T3-01** (`[x]`): миграция `companies_gym_profiles` (с `latitude`/`longitude` — решение разработчика), enum `ModerationStatus`, сущность `GymProfile` (переходы draft→pending→approved/rejected, `update` сбрасывает в draft), исключение `InvalidGymProfileTransition`, `GymProfileTest`.
- Тесты: 285 зелёные, `analyse` и `deps` чистые.
- `composer lint` падает на 10 файлах, оставшихся с T2-21…T2-23 (только форматирование) — задача **T3-00**.

## Точный следующий шаг

1. Выполнить **T3-00** (Pint-форматирование 10 файлов) отдельным коммитом.
2. Затем **T3-02**: `GymProfileRepositoryInterface` + `EloquentGymProfileRepository` + `GymProfileModel` (таблица `companies_gym_profiles`, поле `company_id` брать из `gyms`) + биндинг в `CompaniesServiceProvider` + тест.

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
