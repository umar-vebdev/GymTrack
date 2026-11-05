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

- **T3-09c [x]**: добавлен `PATCH /api/v1/business/gyms/{gym}/publication`, обязательное поле is_public (правило required/boolean), ответ 200 через GymProfileResource.
- `GymProfilePolicy::setPublication` проверяет владельца и компанию зала; без доступа → 403, чужой/отсутствующий зал → 404. Контроллер только связывает запрос, проверку прав, сценарий и ответ.
- `SetGymPublication` выполняет транзакцию, блокирует строку зала через findByIdForUpdate, повторно проверяет компанию, читает витрину после блокировки, вызывает setPublication и сохраняет.
- Включение только approved (остальные статусы → 400); выключение в любом статусе. Повторное значение допустимо, содержимое/район и модерация сохраняются. Без записи витрины включение → 400, выключение создаёт private draft (как существующая трактовка отсутствующей витрины).
- Добавлено 15 тестов состояний, повторов, прав, чужого зала, валидации, защиты на уровне сценария и отката частичной записи при ошибке БД.
- **389 тестов, 1790 проверок**; lint/analyse/test/deps проходят. OpenAPI `docs/api/gym-profiles.yaml` дополнен PATCH, YAML разобран без ошибок. Новых пакетов, миграций и изменений ТЗ нет; local-БД не менялась.
- При чтении старых сценариев подтверждено, что UpdateGymProfile/SubmitGymProfileForModeration/ModerateGymProfile пока не используют общую блокировку строки зала, которую уже используют публикация и фото. Добавлена отдельная **T3-09d**, выполнить до публичного каталога, чтобы исключить запись устаревшего состояния при одновременных изменениях.

## Текущая задача и точный следующий шаг

1. **T3-09d [ ] — общая блокировка при изменениях витрины.** Перечитать техническое ТЗ 8, 9.1 и D-010; проверить три сценария UpdateGymProfile/SubmitGymProfileForModeration/ModerateGymProfile и их тесты. Пометить задачу [~].
2. Перенести чтение/изменение витрины в транзакцию с findByIdForUpdate; читать витрину только после блокировки. Сохранить существующие ответы, права и работу аудита, проверить события и последствия ошибки сохранения.
3. Запустить тесты состояний и сохранности публикации, lint/analyse/test/deps; обновить документы и коммит. Дополнительное согласование не нужно: задача найдена в текущем этапе и записана по AGENTS.md 5.2.
4. Затем T3-10/T3-11 — публичные контракты чтения Companies/Memberships, после них Catalog. План E3-B согласован; карта остаётся в E3-C.

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
