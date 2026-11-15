# Handoff — GymTrack

**Обновлено:** 2026-11-15
**Текущий этап:** Этап 6 завершён (`E6-A`, `E6-B` и `E6-C` выполнены; открытых задач в `docs/TASKS.md` нет)
**Текущая задача:** нет (все задачи `T6-11a`–`T6-15` эпика `E6-C` закрыты)

## Что сделано в последней сессии (`E6-C`)

- **`D-019`:** зафиксирован план эпика `E6-C` (`Could`-функции).
- **`T6-11a`–`T6-11b`:** опциональная геопроверка чекина (`geo_check_enabled`, `geo_check_radius_meters`, `WithinGymGeoFenceRule`, координаты в `POST /api/v1/client/visits/check-in`).
- **`T6-12a`–`T6-12b`:** личный динамический QR клиента (`GET /api/v1/client/visits/qr`, `POST /api/v1/business/gyms/{id}/check-in-qr`).
- **`T6-13a`–`T6-13b`:** отзывы и рейтинг залов (`ClientGymVisitCheckInterface`, `companies_gym_reviews`, `UpsertGymReview`, `ListPublicGymReviews`, `PUT /api/v1/client/gyms/{publicId}/review`, `GET /api/v1/public/gyms/{publicId}/reviews`, `rating_avg`/`reviews_count` в каталоге).
- **`T6-14`:** массовая рассылка объявлений зала (`ClientLookupInterface::listRecipientsForCompany`, `NotificationType::GymAnnouncement`, `SendGymAnnouncement`, `POST /api/v1/business/notifications/announcements`).
- **`T6-15`:** HTTP-адаптеры SMS и Push шлюзов (`HttpSmsGateway`, `HttpNotificationsSmsGateway`, `HttpPushGateway`, `SMS_DRIVER`/`PUSH_DRIVER=log|http` в `config/services.php` и `.env.example`, `IdentityServiceProvider`, `NotificationsServiceProvider`, обновление `README.md`).

## Актуальное состояние проверок

- `composer lint` (`pint --test`) — ✓
- `composer analyse` (`phpstan`, level 8) — ✓ (0 errors)
- `composer deps` (`deptrac`) — ✓ (0 violations)
- `docker compose exec -T app composer test -- --compact --colors=never` — ✓ (`560 passed, 3864 assertions`)

## Точный следующий шаг

1. Приступить к релизному развёртыванию или сформулировать следующий этап / продуктовые задачи при участии разработчика.
