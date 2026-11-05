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

- **T3-09b [x]**: district передаётся из существующего `PUT /api/v1/business/gyms/{gym}/profile` через UpdateGymProfileCommand и сценарий в GymProfile::update. Аргумент команды необязательный (null по умолчанию), старые вызовы совместимы.
- FormRequest: nullable string max:100. Стандартная обработка Laravel убирает пробелы по краям и превращает пустую строку в null. PUT заменяет содержимое: отсутствие district тоже очищает район.
- Общий `GymProfileResource` теперь возвращает district и is_public (также в уже существующих ответах отправки/модерации/очереди, где используется этот ресурс).
- Успешная правка меняет район, возвращает витрину в draft и отключает публикацию; is_public во входном PUT не управляет публикацией. В pending правка запрещена, права владельца и чужая компания проверяются прежним способом.
- Добавлено 3 теста и усилены существующие: очистка поля, Unicode до 100 символов, длина 101/неверный тип → 422 без изменения опубликованной витрины, pending, отключение публикации при успехе.
- **374 теста, 1683 проверки**; lint/analyse/test/deps проходят. OpenAPI `docs/api/gym-profiles.yaml` добавлен и разобран YAML-парсером. Новых пакетов, миграций и правок ТЗ нет.
- Рабочая local-БД не менялась; почти все модульные миграции там Pending.

## Текущая задача и точный следующий шаг

1. **T3-09c [ ] — включение/выключение публикации владельцем.** План E3-B принят, повторное согласование не нужно. Перечитать бизнес-ТЗ 4.2, техническое ТЗ 7.1–7.2, 8 и D-010, затем отметить задачу [~].
2. Добавить `PATCH /api/v1/business/gyms/{gym}/publication` с обязательным boolean is_public. Проверять владельца и компанию в Policy; FormRequest — валидация; сценарий — транзакция с блокировкой строки зала, вызов GymProfile::setPublication и сохранение.
3. Включить можно только approved, выключить в любом статусе; повторное одинаковое действие допустимо. Ответ через GymProfileResource. Проверить права, чужой/отсутствующий зал, состояния, повторные вызовы; дополнить OpenAPI.
4. Выполнить все проверки, обновить документы и коммит. Далее T3-10/T3-11 — публичные контракты чтения Companies/Memberships, затем Catalog. Карта — E3-C.

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
