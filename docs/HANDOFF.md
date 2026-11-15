# Handoff — GymTrack

**Обновлено:** 2026-11-15
**Текущий этап:** Этап 6 (`E6-A` и `E6-B` завершены; активен `E6-C` по решению `D-019`)
**Текущая задача:** `T6-11b` — Координаты в `POST /api/v1/client/visits/check-in` и настройках зала (`Visits` + `Companies`)

## Что сделано в последней сессии (`E6-C`)

- **`D-019`:** зафиксирован подтверждённый план эпика `E6-C` (`T6-11a`–`T6-15`).
- **`T6-11a`:** добавлены настройки геопроверки в `gyms` (`geo_check_enabled`, `geo_check_radius_meters` 50..5000), передача параметров и координат витрины в `GymQrSummary`/`EloquentGymQrLookup`, правило `WithinGymGeoFenceRule` (формула гаверсинуса) и unit-тесты `CheckInGeoFenceTest`.

## Актуальное состояние проверок

- `composer lint` (`pint --test`) — ✓
- `composer analyse` (`phpstan`, level 8) — ✓ (0 errors)
- `composer deps` (`deptrac`) — ✓ (0 violations)
- `docker compose exec -T app composer test -- --compact --colors=never` — ✓ (`540 passed, 3670 assertions`)

## Точный следующий шаг

1. Выполнить `T6-11b`: принять `geo_check_enabled`/`geo_check_radius_meters` в `CreateGym`/`UpdateGym`/`GymsController`/`GymResource`, прокинуть `latitude`/`longitude` в `CheckInByQr`, обновить `docs/api/companies.yaml` и `docs/api/visits-analytics.yaml`, написать `CheckInByQrGeoEndpointTest`.
2. Продолжить `T6-12a`–`T6-15`.
