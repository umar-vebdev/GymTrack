# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.

**Обновлено:** 2026-11-07
**Этап:** 3 — Каталог (витрины залов, модерация, публичный каталог, карта)
**Ветка:** main

## Текущее состояние

- Этапы 0–2 завершены. Эпики **E3-A и E3-B завершены** (`T3-01`…`T3-14b` в `docs/TASKS.md`).
- Эпик **E3-C (Карта и гео-поиск)**: план подтверждён 2026-11-07; `T3-15` готова, следующая `T3-16`.

## Последняя сессия

- **T3-15 [x]**: Value Object `GeoBounds` и `GeoRadius` в `Companies/Contracts`, необязательные `bounds`/`radius` в `PublicGymFilter`, фильтрация в `EloquentPublicGymQuery` (`BETWEEN` + гаверсинус, витрины без координат отсекаются только при гео-фильтре). Попутно — только форматирование Pint у `tests/Feature/Catalog/GetPublicGymTest.php`.
- **421 тест, 2138 проверок**; `composer lint`, `composer analyse`, `composer test`, `composer deps` проходят.

## Текущая задача и точный следующий шаг

1. Приступить к **T3-16 [ ] — гео-параметры в `SearchGyms` и `GET /api/v1/public/gyms`**: пометить `[~]`; пробросить `lat`/`lng`/`radius_km` и `sw_lat`/`sw_lng`/`ne_lat`/`ne_lng` через `SearchGymsQuery` → `SearchGyms` → `PublicGymFilter` (создавать `GeoBounds`/`GeoRadius` из запроса); правила комплектности и диапазонов — в `SearchGymsRequest` (422), чтобы `InvalidArgumentException` из Value Object не доходили до 500; OpenAPI `docs/api/public-catalog.yaml`; тесты в `ListPublicGymsTest`.

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
