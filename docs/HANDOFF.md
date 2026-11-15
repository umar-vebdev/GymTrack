# Handoff — GymTrack

**Обновлено:** 2026-11-15
**Текущий этап:** Этап 6 (`E6-A` и `E6-B` завершены; активен `E6-C` по решению `D-019`)
**Текущая задача:** `T6-14` — Рассылка объявлений зала (`Notifications` + `Clients`)

## Что сделано в последней сессии (`E6-C`)

- **`D-019`:** зафиксирован подтверждённый план эпика `E6-C` (`T6-11a`–`T6-15`).
- **`T6-11a`–`T6-11b`:** геопроверка залов при чекине (`WithinGymGeoFenceRule`, настройки филиалов и координаты в `check-in`).
- **`T6-12a`–`T6-12b`:** личный динамический QR клиента (`GET /api/v1/client/visits/qr`, `POST /api/v1/business/gyms/{id}/check-in-qr`).
- **`T6-13a`–`T6-13b`:** отзывы и рейтинг залов (`ClientGymVisitCheckInterface`, `companies_gym_reviews`, `UpsertGymReview`/`ListPublicGymReviews`, `PUT /api/v1/client/gyms/{publicId}/review`, `GET /api/v1/public/gyms/{publicId}/reviews`, `rating_avg`/`reviews_count` в каталоге, `docs/api/public-catalog.yaml`).

## Актуальное состояние проверок

- `composer lint` (`pint --test`) — ✓
- `composer analyse` (`phpstan`, level 8) — ✓ (0 errors)
- `composer deps` (`deptrac`) — ✓ (0 violations)
- `GymReviewsEndpointsTest|SearchGymsTest|ListPublicGymsTest|ShowPublicGymTest|PublicCatalogIdsTest` — ✓ (24 passed, 367 assertions)

## Точный следующий шаг

1. Выполнить `T6-14` (добавить `listRecipientsForCompany` в `ClientLookupInterface`/`EloquentClientLookup`, `NotificationType::GymAnnouncement`, `SendGymAnnouncement`, `POST /api/v1/business/notifications/announcements`, обновить `docs/api/visits-analytics.yaml`, написать `SendGymAnnouncementTest`).
2. Завершить `T6-15` (HTTP-адаптеры SMS/Push, README, полный прогон `composer test`).
