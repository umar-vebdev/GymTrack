# Handoff — GymTrack

**Обновлено:** 2026-11-15
**Текущий этап:** Этап 6 (`E6-A` и `E6-B` завершены; активен `E6-C` по решению `D-019`)
**Текущая задача:** `T6-12a` — Генерация и проверка личного QR клиента + сценарии (`Visits`)

## Что сделано в последней сессии (`E6-C`)

- **`D-019`:** зафиксирован подтверждённый план эпика `E6-C` (`T6-11a`–`T6-15`).
- **`T6-11a`:** добавлены настройки геопроверки в `gyms`, правило `WithinGymGeoFenceRule` и `CheckInGeoFenceTest`.
- **`T6-11b`:** добавлена настройка геопроверки в API филиалов (`StoreGymRequest`/`UpdateGymRequest`/`GymResource`) и координаты `latitude`/`longitude` в `POST /api/v1/client/visits/check-in` с кодами ошибок `GEO_LOCATION_REQUIRED` и `OUTSIDE_GYM_GEOFENCE`, обновлены `docs/api/companies.yaml` и `docs/api/visits-analytics.yaml`, добавлен `CheckInByQrGeoEndpointTest`.

## Актуальное состояние проверок

- `composer lint` (`pint --test`) — ✓
- `composer analyse` (`phpstan`, level 8) — ✓ (0 errors)
- `composer deps` (`deptrac`) — ✓ (0 violations)
- Целевые тесты (`CheckInByQrGeoEndpointTest`, `ManageGymsEndpointTest`, `CheckInByQrEndpointTest`) — ✓

## Точный следующий шаг

1. Выполнить `T6-12a` (`VisitMethod::ClientQr`, `ClientQrPayload`, `QrTokenServiceInterface`/`HmacQrTokenService`, `GenerateClientQr`, `CheckInByClientQr`, `ClientQrUseCasesTest`).
2. Продолжить `T6-12b`–`T6-15`.
