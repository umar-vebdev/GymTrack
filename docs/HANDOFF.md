# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.

**Обновлено:** 2026-11-06
**Этап:** 3 — Каталог (витрины залов, модерация, публичный каталог, карта)
**Ветка:** main

## Текущее состояние

- Этапы 0–2 завершены. Эпики **E3-A и E3-B завершены** (`T3-01`…`T3-14b` в `docs/TASKS.md`).
- Эпик **E3-C (Карта и гео-поиск)** раскрыт в задачи `T3-15` и `T3-16` в `docs/TASKS.md` и предложен разработчику на подтверждение (раздел 4.3 `AGENTS.md`).

## Последняя сессия

- **T3-12 [x]**: создан и зарегистрирован `CatalogServiceProvider`, реализованы `SearchGymsQuery` и `SearchGyms`, добавлены 5 тестов в `SearchGymsTest`.
- **T3-13 [x]**: добавлен публичный эндпоинт `GET /api/v1/public/gyms` без авторизации (`SearchGymsRequest`, `ListPublicGymsController`, `PublicGymResource`, OpenAPI `docs/api/public-catalog.yaml`) и 3 теста в `ListPublicGymsTest`.
- **T3-14a [x]**: реализованы `GetPublicGym` и `PublicGymCardResult` в `Catalog/Application`, добавлены 3 теста в `GetPublicGymTest`.
- **T3-14b [x]**: добавлен публичный эндпоинт карточки зала `GET /api/v1/public/gyms/{publicId}` без авторизации (`ShowPublicGymController`, `PublicGymCardResource`), дополнена OpenAPI-спецификация `docs/api/public-catalog.yaml`, добавлены 3 теста в `ShowPublicGymTest`.
- **418 тестов, 2114 проверок**; `composer lint`, `composer analyse`, `composer test`, `composer deps` проходят.

## Текущая задача и точный следующий шаг

1. Дождаться подтверждения разработчиком плана эпика **E3-C** (`T3-15`, `T3-16`).
2. После подтверждения приступить к **T3-15 [ ] — Гео-фильтр прямоугольной области карты (`bounds`) и радиуса (`lat`, `lng`, `radius_km`) в `PublicGymFilter` и `EloquentPublicGymQuery`**: пометить `[~]`, реализовать фильтрацию по прямоугольнику и формуле гаверсинуса с отсечением витрин без координат, покрыть тестами в `PublicGymQueryTest`.

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
