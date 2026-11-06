# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.

**Обновлено:** 2026-11-06
**Этап:** 3 — Каталог (витрины залов, модерация, публичный каталог, карта)
**Ветка:** main

## Текущее состояние

- Этапы 0–2 завершены. Эпик **E3-A завершён** (T3-01…T3-07 в `docs/TASKS.md`). E3-B раскрыт и согласован 2026-11-04; E3-C ещё не раскрыт.
- **T3-08…T3-09d** (`[x]`): публичные идентификаторы ULID (`public_id`) у залов и типов абонементов, поля `district` и `is_public` в витрине зала, редактирование района в `PUT /api/v1/business/gyms/{gym}/profile`, управление публикацией `PATCH /api/v1/business/gyms/{gym}/publication`, согласованная блокировка строки зала (`findByIdForUpdate`) во всех сценариях мутации витрины.
- **T3-10** (`[x]`): контракт `PublicGymQueryInterface`, DTO `PublicGymProfile` и `PublicGymFilter`, реализация `EloquentPublicGymQuery` в модуле `Companies`.
- **T3-11** (`[x]`): контракт `MembershipCatalogQueryInterface`, DTO `PublicMembershipType`, реализация `EloquentMembershipCatalogQuery` в модуле `Memberships`.
- **T3-12** (`[x]`): подключён модуль `Catalog` (`CatalogServiceProvider`, `SearchGyms`, `SearchGymsQuery`, `routes.php`).
- **T3-13** (`[x]`): публичный список залов `GET /api/v1/public/gyms` (`SearchGymsRequest`, `ListPublicGymsController`, `PublicGymResource`, OpenAPI `docs/api/public-catalog.yaml`).
- **T3-14a** (`[x]`): сценарий публичной карточки `GetPublicGym` и результат `PublicGymCardResult`.

## Последняя сессия

- **T3-12 [x]**: создан и зарегистрирован `CatalogServiceProvider`, реализованы `SearchGymsQuery` и `SearchGyms`, добавлены 5 тестов в `SearchGymsTest`.
- **T3-13 [x]**: добавлен публичный эндпоинт `GET /api/v1/public/gyms` без авторизации (`SearchGymsRequest`, `ListPublicGymsController`, `PublicGymResource`, OpenAPI `docs/api/public-catalog.yaml`) и 3 теста в `ListPublicGymsTest`.
- **T3-14a [x]**: реализованы `GetPublicGym` и `PublicGymCardResult` в `Catalog/Application`: карточка зала ищется по ULID через `PublicGymQueryInterface::findByPublicId`, и только для опубликованной витрины подгружаются активные абонементы через `MembershipCatalogQueryInterface::listActiveForCompany`. Восстановлено форматирование Pint у 4 файлов после коммита `d3511dc`. Добавлены 3 теста в `GetPublicGymTest`.
- **415 тестов, 2056 проверок**; `composer lint`, `composer analyse`, `composer test`, `composer deps` проходят.

## Текущая задача и точный следующий шаг

1. **T3-14b [ ] — Публичная карточка зала.** Перечитать Бизнес-ТЗ 4.2, 7, Техническое ТЗ 7.1–7.2, 8 и решение D-010. Пометить задачу `[~]` в `docs/TASKS.md`.
2. Создать `Catalog/Presentation/ShowPublicGymController.php`, ресурс карточки `Catalog/Presentation/Resources/PublicGymCardResource.php` (или расширить `PublicGymResource` полем `membership_types`), добавить маршрут `GET /api/v1/public/gyms/{publicId}` без авторизации в `Catalog/Presentation/routes.php`.
3. При отсутствии или скрытии зала возвращать `404` (`NOT_FOUND`); проверить отсутствие секретов (`qr_secret`) и внутренних идентификаторов (`gym_id`, `company_id`); дополнить `docs/api/public-catalog.yaml`.
4. Написать `ShowPublicGymTest`, прогнать `lint`, `analyse`, `test`, `deps`, обновить `TASKS.md` и `HANDOFF.md`, сделать коммит.

## Ранее реализовано: фотографии

- `POST /api/v1/business/gyms/{gym}/photos`: jpeg/png/webp до 5 МБ, максимум 10; только владелец, pending запрещён, изменение → draft.
- `DELETE /api/v1/business/gyms/{gym}/photos/{photo}`: общий с загрузкой lock строки зала, проверка компании/зала/фото, сброс модерации; успех 200 с `data: null`.
- Удаление восстанавливает файл при перехваченном сбое; аварийное завершение процесса не покрывается. Отсутствующий файл не мешает удалить запись (D-009, допущение ждёт подтверждения).
- OpenAPI: `docs/api/gym-photos.yaml`, `docs/api/gym-profiles.yaml`, `docs/api/public-catalog.yaml`.

## Окружение: важное про Docker и Git

- Из песочницы сокет Docker недоступен, `.git` доступен только для чтения: Docker и запись git требуют `BypassSandbox: true`. PHPStan вне песочницы нужен локальный сокет рабочих процессов; использовать тот же режим.

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
