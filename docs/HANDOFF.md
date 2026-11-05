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

- **T3-09d [x]**: сценарии `UpdateGymProfile`, `SubmitGymProfileForModeration` и `ModerateGymProfile` переведены на выполнение в `DB::transaction` с блокировкой строки зала через `findByIdForUpdate` до чтения витрины (`findByGymId`), события аудита модерации отправляются после фиксации транзакции.
- **T3-10 [x]**: в `Companies/Contracts` добавлены `PublicGymQueryInterface`, `PublicGymProfile` и `PublicGymFilter`, реализован `EloquentPublicGymQuery` и зарегистрирован в `CompaniesServiceProvider` (чтение только `approved` + `is_public = true` без `CompanyScope`, карточка по ULID, поиск по подстроке названия с экранированием `%`/`_`/`\`, фильтры района, всех удобств и `companyIds`, пагинация по `gyms.public_id ASC` и пакетная загрузка фото без N+1).
- **T3-11 [x]**: в `Memberships/Contracts` добавлены `MembershipCatalogQueryInterface` и `PublicMembershipType` (только публичный `public_id` ULID, название, цена в минимальных единицах, валюта и действующие правила без внутренних `id`/`company_id`), реализован `EloquentMembershipCatalogQuery` и зарегистрирован в `MembershipsServiceProvider`.
- Контракт `MembershipCatalogQueryInterface` возвращает активные типы абонементов компании по `public_id ASC` и выбирает уникальные `company_id`, у которых есть хотя бы один активный тип указанной валюты во включительном диапазоне `[price_min, price_max]` без смешивания валют и без `CompanyScope`. Добавлены 3 теста в `MembershipCatalogQueryTest`.
- **404 теста, 1922 проверки**; `composer lint`, `composer analyse`, `composer test`, `composer deps` проходят.

## Текущая задача и точный следующий шаг

1. **T3-12 [ ] — Модуль Catalog и поиск залов.** Перечитать Бизнес-ТЗ 7, Техническое ТЗ 3.3–3.7, 14 и решение D-010. Пометить задачу `[~]` в `docs/TASKS.md`.
2. Подключить `CatalogServiceProvider` в `bootstrap/providers.php`, создать `Catalog/Application/SearchGyms.php`, `SearchGymsQuery.php` и заготовку `Catalog/Presentation/routes.php`.
3. В `SearchGyms` при наличии фильтра по цене/валюте получать подходящие `companyIds` через `MembershipCatalogQueryInterface::findCompanyIdsByPriceRange`, затем запрашивать страницу витрин через `PublicGymQueryInterface::search` (при пустом списке подходящих компаний возвращать пустой результат без утечки всех залов).
4. Написать `SearchGymsTest` (включая проверку архитектурных границ модуля `Catalog`), прогнать `lint`, `analyse`, `test`, `deps`, обновить `TASKS.md` и `HANDOFF.md`, сделать коммит.

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
