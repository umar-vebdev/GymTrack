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

- **T3-06c [x]**: добавлен `DELETE /api/v1/business/gyms/{gym}/photos/{photo}`. Успех → 200 с `data: null`; чужой зал, фотография другого зала или отсутствующая запись → 404; не владелец → 403; pending → 400.
- `DeleteGymPhoto` блокирует строку зала тем же способом, что загрузка; проверяет компанию и принадлежность фото залу. Запись и статус витрины изменяются в транзакции. `GymProfile::markContentChanged()` возвращает витрину в draft, очищая дату публикации и причину отклонения.
- Файл удаляется после операций БД, до фиксации транзакции. Содержимое сохраняется в памяти на время удаления для восстановления при перехваченном сбое. Сбой удаления откатывает БД; частичное удаление файла восстанавливается. Аварийное завершение процесса не покрывается этим механизмом.
- Если файл уже отсутствует, удаление записи допускается; повторное удаление записи → 404 (малое допущение D-009).
- `GymPhotoPolicy` переиспользует общие проверки владельца и компании для загрузки и удаления; принадлежность фото залу дополнительно проверяет сценарий.
- OpenAPI `docs/api/gym-photos.yaml` дополнен удалением; YAML разобран без ошибок. Новых зависимостей и изменений ТЗ нет.
- Добавлено 10 тестов удаления: права, чужие компании/залы, pending, повтор, отсутствующий файл, ошибки БД и частичный сбой хранилища. **347 тестов, 1503 проверки**; lint/analyse/test/deps проходят.
- Рабочую БД не меняли; миграции проверены на тестовой БД.

## Текущая задача и точный следующий шаг

1. **T3-07 [ ]** — аудит событий модерации. Перечитать техническое ТЗ 3.5, 8; проверить текущие слушатели Audit и подписки в `AuditServiceProvider`, события `GymProfileApproved` / `GymProfileRejected`.
2. Обеспечить неизменяемые записи об одобрении/отклонении: кто, компания, зал, причина. Добавить тесты и выполнить lint/analyse/test/deps.
3. После T3-07 эпик E3-A будет завершён. Раскрыть E3-B (модуль Catalog и публичный API) по ТЗ и показать список задач разработчику для подтверждения до реализации. E3-C — следующий эпик.

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
