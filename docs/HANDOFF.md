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

## Последняя сессия

- **T3-12 [x]**: создан и зарегистрирован `CatalogServiceProvider` в `bootstrap/providers.php`, добавлены заготовка `Catalog/Presentation/routes.php`, DTO `SearchGymsQuery` (нормализация параметров, проверка неотрицательности цен и обязательности `currency` при наличии `priceMin`/`priceMax`) и Use Case `SearchGyms`.
- В `SearchGyms` при наличии фильтра по валюте/цене сначала запрашиваются подходящие `companyIds` через `MembershipCatalogQueryInterface::findCompanyIdsByPriceRange` (при пустом результате сразу возвращается `['items' => [], 'total' => 0]` без вызова `PublicGymQueryInterface::search`), затем витрины выбираются через `PublicGymQueryInterface::search`.
- Восстановлено форматирование Pint у 3 файлов модуля `Memberships` после коммита `edbf78c`. Добавлены 5 тестов в `SearchGymsTest` (включая проверку архитектурных границ модуля `Catalog`).
- **409 тестов, 1941 проверка**; `composer lint`, `composer analyse`, `composer test`, `composer deps` проходят.

## Текущая задача и точный следующий шаг

1. **T3-13 [ ] — Публичный список залов.** Перечитать Бизнес-ТЗ 7, Техническое ТЗ 7.1–7.2, 8 и решение D-010. Пометить задачу `[~]` в `docs/TASKS.md`.
2. Создать `Catalog/Presentation/Http/Requests/SearchGymsRequest.php`, `Catalog/Presentation/Http/Resources/PublicGymSummaryResource.php` (или `PublicGymResource.php`), `Catalog/Presentation/Http/Controllers/ListPublicGymsController.php` и подключить маршрут `GET /api/v1/public/gyms` без авторизации в `Catalog/Presentation/routes.php`.
3. Валидировать `q`, `amenities`, `district`, `price_min`, `price_max`, `currency` (обязателен при `price_min`/`price_max`), `page`, `per_page`; возвращать `data` + `meta` пагинации без внутренних `companyId` и приватных полей; описать эндпоинт в OpenAPI (`docs/api/public-catalog.yaml`).
4. Написать `ListPublicGymsTest`, прогнать `lint`, `analyse`, `test`, `deps`, обновить `TASKS.md` и `HANDOFF.md`, сделать коммит.

## Ранее реализовано: фотографии

- `POST /api/v1/business/gyms/{gym}/photos`: jpeg/png/webp до 5 МБ, максимум 10; только владелец, pending запрещён, изменение → draft.
- `DELETE /api/v1/business/gyms/{gym}/photos/{photo}`: общий с загрузкой lock строки зала, проверка компании/зала/фото, сброс модерации; успех 200 с `data: null`.
- Удаление восстанавливает файл при перехваченном сбое; аварийное завершение процесса не покрывается. Отсутствующий файл не мешает удалить запись (D-009, допущение ждёт подтверждения).
- OpenAPI: `docs/api/gym-photos.yaml`, `docs/api/gym-profiles.yaml`.

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
