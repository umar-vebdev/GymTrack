# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.

**Обновлено:** 2026-11-05
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

- **T3-09a [x]**: миграция `2026_11_05_000000_add_catalog_fields_to_gym_profiles_table.php` добавляет `district` (nullable varchar(100)) и `is_public` (boolean default false). Существующие витрины, в том числе одобренные, не публикуются автоматически. Откат удаляет только новые поля.
- `GymProfile`: добавлены district/isPublic с начальными null/false, getters, `setPublication(bool)` — включение только approved, выключение в любом статусе без изменения решения модерации. Конструктор запрещает isPublic=true в других статусах. Старые вызовы конструктора/update совместимы благодаря необязательным аргументам в конце.
- `update(..., ?string $district = null)` сохраняет район; `markContentChanged()` отключает публикацию. Изменение фото сохраняет район; повторное одобрение не включает публикацию.
- Модель и репозиторий сохраняют/восстанавливают оба поля, is_public приводится к bool.
- Добавлено 14 тестов доменных правил, хранения и отката миграции; усилены существующие тесты загрузки/удаления фото: публикация отключается при успехе и сохраняется при ошибках БД/файлов.
- **371 тест, 1643 проверки**; lint/analyse/test/deps проходят. Новых пакетов, решений и правок ТЗ нет. HTTP-интерфейс новых полей в этой задаче не добавлялся.
- Миграции проверены на тестовой БД; обычная local-БД не менялась, почти все модульные миграции там Pending.

## Текущая задача и точный следующий шаг

1. **T3-09b [ ] — редактирование района владельцем.** План E3-B принят, повторное согласование не нужно. Перечитать бизнес-ТЗ 4.2, 7, техническое ТЗ 7.2, 8 и D-010, затем отметить задачу [~].
2. Добавить district в существующие UpdateGymProfileCommand/Use Case/контроллер/FormRequest/Resource: nullable-строка до 100 символов; новые поля district/is_public возвращать в бизнес-представлении витрины. Использовать уже добавленный аргумент district у GymProfile::update.
3. Проверить сохранение района, неверный формат/длину, права, pending и отключение публикации при правке. Выполнить lint/analyse/test/deps, обновить документы и коммит.
4. Затем T3-09c — PATCH публикации владельцем (сценарий, Policy, FormRequest, контроллер, маршрут). Далее публичные контракты и Catalog по согласованному плану. Карта — E3-C.

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
