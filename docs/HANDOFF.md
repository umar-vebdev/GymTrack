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
- **T3-05b** (`[x]`): Очередь витрин для платформы: `GymProfileRepositoryInterface::paginateByStatus`, `EloquentGymProfileRepository::paginateByStatus`, Use Case `ListGymProfilesForModeration`, эндпоинт `GET /api/v1/platform/gym-profiles?status=pending` (проверка `is_platform_admin`, фильтрация по статусу, пагинация), `ListGymProfilesTest` (6), `GymProfileRepositoryTest` (+1). Тесты: 316 зелёные, lint/analyse/deps чистые.

## Последняя сессия

- **T3-00b [x]**: после сверки обнаружено, что 31b6283 нарушил Pint в четырёх файлах очереди модерации. Исправлено отдельным коммитом.
- **T3-06a [x]**: добавлены таблица `companies_gym_photos`, неизменяемый `GymPhoto`, `GymPhotoRepositoryInterface`, Eloquent-реализация, модель с `CompanyScope`, привязка в провайдере.
- Хранение: создание проверяет компанию зала; чтение/удаление фильтруются по `company_id` и `gym_id`; порядок — `order`, затем `id`. Репозиторий удаляет только запись: файлом управляет будущий сценарий удаления.
- Добавлено 4 теста хранения, порядка, изоляции компаний и ограничения текущей компании. Полный набор: **320 тестов, 1353 проверки**; lint/analyse/deps проходят.
- T3-06 разбита на T3-06a/b/c по правилу размера задачи. Новых зависимостей нет.

## Текущая задача и точный следующий шаг

- **T3-06b1 [x]**: `UploadGymPhoto` сохраняет файл на `public` и запись в одной транзакции БД с блокировкой зала (`findByIdForUpdate`). Максимум 10 фотографий, порядок после последнего; `GymProfile::markContentChanged()` возвращает витрину в draft, запрещает pending. Файл очищается при ошибке БД. 327 тестов зелёные (1373 проверки), lint/analyse/deps проходят.
- **Следующий шаг T3-06b2 [ ]**: HTTP `POST /api/v1/business/gyms/{gym}/photos`, проверка прав в Policy, FormRequest с jpeg/png/webp до 5 МБ, ответ 201. Повторно прочитать бизнес-ТЗ 4.2, 7 и техническое ТЗ 7–8.
- Затем T3-06c — удаление фотографии, T3-07 — аудит модерации.

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
