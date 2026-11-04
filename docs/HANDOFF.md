# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.

**Обновлено:** 2026-11-04
**Этап:** 3 — Каталог (витрины залов, модерация, публичный каталог, карта)
**Ветка:** main

## Текущее состояние

- Этапы 0–2 завершены. Эпик **E3-A завершён** (T3-01…T3-07 в `docs/TASKS.md`). E3-B и E3-C ещё не раскрыты.
- **T3-00** (`[x]`): проект отформатирован Pint, `composer lint` чистый.
- **T3-01** (`[x]`): миграция `companies_gym_profiles` (с `latitude`/`longitude`), enum `ModerationStatus`, сущность `GymProfile` (draft→pending→approved/rejected, `update` сбрасывает в draft), `InvalidGymProfileTransition`.
- **T3-02** (`[x]`): `GymProfileRepositoryInterface` (`findByGymId`, `save` = upsert), `EloquentGymProfileRepository`, `GymProfileModel`, биндинг в `CompaniesServiceProvider`.
- **T3-03** (`[x]`): `UpdateGymProfile` + `UpdateGymProfileCommand`, `PUT /api/v1/business/gyms/{gym}/profile` (только владелец, чужой зал → 404, `pending` → 400, координаты только парой), `GymProfileResource`, `GymNotFound`, `UpdateGymProfileTest` (7). Тесты: 295 зелёные, lint/analyse/deps чистые.
- **T3-04** (`[x]`): `SubmitGymProfileForModeration`, `POST /api/v1/business/gyms/{gym}/profile/submit` (только владелец, чужой зал → 404, неполная/повтор → 400), `SubmitGymProfileTest` (6). Тесты: 301 зелёные, lint/analyse/deps чистые.
- **T3-05a** (`[x]`): Use Case `ModerateGymProfile`, аудируемые события `GymProfileApproved` / `GymProfileRejected` в `Companies/Contracts`, `POST /api/v1/platform/gyms/{gym}/moderate` (`is_platform_admin`, approve/reject, reason при reject), `ModerateGymProfileTest` (8). Тесты: 309 зелёные, lint/analyse/deps чистые.
- **T3-05b** (`[x]`): Очередь витрин для платформы: `GymProfileRepositoryInterface::paginateByStatus`, `EloquentGymProfileRepository::paginateByStatus`, Use Case `ListGymProfilesForModeration`, эндпоинт `GET /api/v1/platform/gym-profiles?status=pending` (проверка `is_platform_admin`, фильтрация по статусу, пагинация), `ListGymProfilesTest` (6), `GymProfileRepositoryTest` (+1). Тесты: 316 зелёные, lint/analyse/deps чистые.

## Последняя сессия

- **T3-07 [x]**: подтверждено, что `GymProfileApproved` / `GymProfileRejected` уже реализуют `AuditableEvent`, а `AuditServiceProvider` подписывает общий `AuditEventListener` на этот интерфейс. Дополнительные обработчики не добавлялись, чтобы не дублировать записи.
- Добавлен `tests/Feature/Audit/GymProfileModerationAuditTest.php`: реальные HTTP-запросы без подмены событий проверяют автора, компанию, зал, тип действия, время и причину отклонения.
- Проверены отсутствие записей при 401/403/404/422 и повторном решении (400), сохранность прежних записей после повторной модерации, корректная компания и модератор для разных залов.
- **351 тест, 1550 проверок**; lint/analyse/test/deps проходят. Рабочий код не менялся: существующая связь модулей покрывает требование. Новых зависимостей, изменений схемы и ТЗ нет.
- Эпик E3-A завершён: витрины, модерация и очередь, хранение/загрузка/удаление фотографий, аудит модерации.

## Текущая задача и точный следующий шаг

1. **Следующий эпик E3-B — Catalog и публичный API. Реализация ещё не согласована.** Перечитать бизнес-ТЗ 4.2, 4.3, 7, техническое ТЗ 3.3–3.7, 7.1–7.2, 14 и проверить существующие публичные контракты Companies/Memberships.
2. Раскрыть E3-B в задачи до ~5 рабочих файлов и ~200 строк, с файлами, шагами, командой приёмки; показать список разработчику для подтверждения перед реализацией (AGENTS.md 4.3).
3. При планировании проверить, как хранить район для фильтра каталога: текущая витрина имеет адрес и координаты, но отдельного района нет. Архитектурные изменения согласовать по AGENTS.md 8.3; не добавлять поле или формат API молча.
4. Затем E3-C — карта и геопоиск. Этапы 4–5 не начинать.

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
