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

- **T3-06b1 [x]** (`e1ba3e5`): `UploadGymPhoto` сохраняет файл на `public` и запись в транзакции с блокировкой строки зала через `GymRepositoryInterface::findByIdForUpdate`. Максимум 10 фотографий, порядок после последнего. При ошибке БД новый файл удаляется. Сбой хранилища не создаёт записей.
- `GymProfile::markContentChanged()` — общее правило изменения текста и фотографий: сбрасывает статус в draft, очищает published_at и причину отклонения; pending запрещён. Текст/адрес и прочие данные при загрузке фото сохраняются.
- **T3-06b2 [x]**: `POST /api/v1/business/gyms/{gym}/photos`, multipart-поле `photo`, jpeg/png/webp до 5 МБ. Владелец своей компании → 201 (`data.id/gym_id/url/order/created_at`), чужой/отсутствующий зал → 404, без прав → 403; лимит или pending → 400; невалидный файл → 422.
- Права проверяет `Companies/Presentation/Policies/GymPhotoPolicy.php`, файл — `UploadGymPhotoRequest`. OpenAPI: `docs/api/gym-photos.yaml` (YAML разобран без ошибок).
- Добавлено 7 тестов сценария и 10 HTTP-тестов, включая ошибки хранения и БД, чужой зал, предел 5 МБ, лимит 10 фото, настоящий PNG и текст под видом jpg. **337 тестов, 1457 проверок**; lint/analyse/deps проходят.
- Новых зависимостей и изменений ТЗ нет. Миграция фото проверена на тестовой БД; рабочую БД в этой сессии не меняли.

## Текущая задача и точный следующий шаг

1. **T3-06c [ ]** — удаление фотографии: `DELETE /api/v1/business/gyms/{gym}/photos/{photo}`. Только владелец своего зала; проверить компанию и принадлежность фото залу, удалить запись и файл, сбросить одобренную витрину в draft; pending запрещён. Согласовать блокировку с загрузкой (строка зала).
2. Перечитать бизнес-ТЗ 4.2, 7 и техническое ТЗ 7.1, 8; пометить задачу [~]. Переиспользовать `GymPhotoPolicy`, `GymProfile::markContentChanged()` и репозиторий фотографий. Сам репозиторий удаляет только запись, файлом управляет сценарий.
3. Затем T3-07 — аудит событий модерации. Эпики Catalog E3-B/E3-C ещё не раскрыты.

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
