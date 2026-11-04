# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.

**Обновлено:** 2026-11-04
**Этап:** 3 — Каталог (витрины залов, модерация, публичный каталог, карта)
**Ветка:** main

## Текущее состояние

- Этапы 0–2 завершены. Эпик **E3-A завершён** (T3-01…T3-07 в `docs/TASKS.md`). E3-B раскрыт и согласован 2026-11-04; E3-C ещё не раскрыт.
- **T3-00** (`[x]`): проект отформатирован Pint, `composer lint` чистый.
- **T3-01** (`[x]`): миграция `companies_gym_profiles` (с `latitude`/`longitude`), enum `ModerationStatus`, сущность `GymProfile` (draft→pending→approved/rejected, `update` сбрасывает в draft), `InvalidGymProfileTransition`.
- **T3-02** (`[x]`): `GymProfileRepositoryInterface` (`findByGymId`, `save` = upsert), `EloquentGymProfileRepository`, `GymProfileModel`, биндинг в `CompaniesServiceProvider`.
- **T3-03** (`[x]`): `UpdateGymProfile` + `UpdateGymProfileCommand`, `PUT /api/v1/business/gyms/{gym}/profile` (только владелец, чужой зал → 404, `pending` → 400, координаты только парой), `GymProfileResource`, `GymNotFound`, `UpdateGymProfileTest` (7). Тесты: 295 зелёные, lint/analyse/deps чистые.
- **T3-04** (`[x]`): `SubmitGymProfileForModeration`, `POST /api/v1/business/gyms/{gym}/profile/submit` (только владелец, чужой зал → 404, неполная/повтор → 400), `SubmitGymProfileTest` (6). Тесты: 301 зелёные, lint/analyse/deps чистые.
- **T3-05a** (`[x]`): Use Case `ModerateGymProfile`, аудируемые события `GymProfileApproved` / `GymProfileRejected` в `Companies/Contracts`, `POST /api/v1/platform/gyms/{gym}/moderate` (`is_platform_admin`, approve/reject, reason при reject), `ModerateGymProfileTest` (8). Тесты: 309 зелёные, lint/analyse/deps чистые.
- **T3-05b** (`[x]`): Очередь витрин для платформы: `GymProfileRepositoryInterface::paginateByStatus`, `EloquentGymProfileRepository::paginateByStatus`, Use Case `ListGymProfilesForModeration`, эндпоинт `GET /api/v1/platform/gym-profiles?status=pending` (проверка `is_platform_admin`, фильтрация по статусу, пагинация), `ListGymProfilesTest` (6), `GymProfileRepositoryTest` (+1). Тесты: 316 зелёные, lint/analyse/deps чистые.

## Последняя сессия

- Разработчик поручил выбрать оптимальный вариант и начать E3-B. План T3-08…T3-14b принят целиком, повторное согласование перед каждой задачей не нужно. Правила записаны в **D-010**: district как текст, явное включение владельцем после approved, публичные ULID, фильтр цены в указанной валюте, чтение только через Contracts.
- **T3-08 [x]**: добавлены миграции `2026_11_04_000001_add_public_id_to_gyms_table.php` (Companies) и `2026_11_04_000002_add_public_id_to_membership_types_table.php` (Memberships). public_id: ULID, unique, NOT NULL; существующие записи заполняются порциями по 500.
- `GymModel` и `MembershipTypeModel` используют встроенный `HasUlids` с `uniqueIds() => ['public_id']`. Автоинкремент и тип числового первичного ключа сохранены, обычное обновление не меняет public_id. Поле не добавлено в fillable.
- `tests/Feature/Catalog/PublicCatalogIdsTest.php`: шесть тестов новых ID, заполнения старых записей, ограничений unique/NOT NULL, отката и повторного применения без потери исходных данных.
- **357 тестов, 1593 проверки**; lint/analyse/test/deps проходят. Новых пакетов и правок ТЗ нет.
- Миграции применялись и откатывались только на тестовой БД. Проверено `artisan env`: local. В обычной локальной БД почти все модульные миграции пока Pending, включая новые; её данные в задаче не менялись.

## Текущая задача и точный следующий шаг

1. **T3-09a [ ] — атрибуты публикации витрины.** Перечитать бизнес-ТЗ 4.2, 7, тех-ТЗ 3.4, 6.1 и D-010. Пометить задачу [~].
2. Добавить `district` (nullable, до 100 символов) и `is_public` (false по умолчанию), перенести поля через `GymProfileModel`/репозиторий. Обеспечить совместимость текущих вызовов конструктора/обновления.
3. Включение публикации допустимо только approved; выключение — в любом статусе. `markContentChanged()` отключает публикацию. Проверить сохранение/чтение и переходы в тестах, выполнить все проверки и коммит.
4. Затем T3-09b (редактирование района), T3-09c (включение/выключение владельцем), остальные задачи согласованного E3-B. Карта/георадиус остаются в E3-C.

## Ранее реализовано: фотографии

- `POST /api/v1/business/gyms/{gym}/photos`: jpeg/png/webp до 5 МБ, максимум 10; только владелец, pending запрещён, изменение → draft.
- `DELETE /api/v1/business/gyms/{gym}/photos/{photo}`: общий с загрузкой lock строки зала, проверка компании/зала/фото, сброс модерации; успех 200 с `data: null`.
- Удаление восстанавливает файл при перехваченном сбое; аварийное завершение процесса не покрывается. Отсутствующий файл не мешает удалить запись (D-009, допущение ждёт подтверждения).
- OpenAPI: `docs/api/gym-photos.yaml`. Рабочая БД в последних сессиях не менялась; миграции проверены на тестовой БД.

## Окружение: важное про Docker и Git

- Из песочницы сокет Docker недоступен, `.git` доступен только для чтения: Docker и запись git требуют `sandbox_permissions=require_escalated`. PHPStan вне песочницы нужен локальный сокет рабочих процессов; использовать тот же режим.

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

## Известные особенности окружения

- Composer в контейнере печатает предупреждение Git о владельце `/var/www`; проверки завершаются успешно. Глобальную конфигурацию Git не меняли.
- `rg` отсутствует: для поиска использовались `find` и стандартные команды.
