# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.
> Цель: новая сессия без истории чата продолжает ровно с этого места. Держать в пределах ~100 строк.

**Обновлено:** 2026-11-02
**Этап:** 2 — Client (эпик E2-D Notifications: push и SMS, напоминания об окончании абонемента)
**Ветка:** main

## Текущая задача

- **T2-16** (`[x]`): каркас модуля `Notifications`, миграция `notifications_logs`, шлюзы `PushGatewayInterface`, `SmsGatewayInterface` и сервис `NotificationSender`.
- Следующая активная задача: **T2-17** (`[ ]`) — Регистрация и отзыв push-токенов клиентских устройств (`POST /api/v1/client/device-tokens`, `DELETE /api/v1/client/device-tokens`).

## Последняя сессия: что сделано

- **T2-16**:
  - Создана архитектура модуля `Notifications` (`Domain`, `Application`, `Infrastructure`, `Presentation`).
  - Миграция таблицы `notifications_logs` (`company_id`, `user_id`, `client_id`, `channel`, `type`, `recipient`, `title`, `body`, `entity_type`, `entity_id`, `status`, `error`, `sent_at`).
  - Доменные сущности и перечисления: `NotificationChannel`, `NotificationType`, `NotificationStatus`, `NotificationLogEntry`.
  - Шлюзы и адаптеры: `PushGatewayInterface`, `SmsGatewayInterface`, `LogPushGateway`, `LogNotificationsSmsGateway`.
  - Модель `NotificationLogModel` и репозиторий `EloquentNotificationLogRepository` с проверкой `alreadySent()`.
  - Сервис приложения `NotificationSender` с гарантированной регистрацией статусов отправки и обработкой ошибок.
  - Провайдер `NotificationsServiceProvider` подключён в `bootstrap/providers.php`.
  - Unit-тесты: `NotificationSenderTest` и `EloquentNotificationLogRepositoryTest`.
- Всего 239 тестов (1051 проверка) зелёные, `lint`/`analyse`/`deps` чистые.

## Точный следующий шаг

1. Перечитать раздел Технического ТЗ 7.1 и Бизнес-ТЗ 5.3 для задачи **T2-17**.
2. Поставить статус `[~]` для **T2-17** в `docs/TASKS.md` и обновить `docs/HANDOFF.md`.
3. Создать миграцию `notifications_device_tokens` (`id`, `user_id`, `token`, `platform`, `created_at`, `updated_at`).
4. Создать модель `DeviceTokenModel`, интерфейс `DeviceTokenRepositoryInterface` и репозиторий `EloquentDeviceTokenRepository`.
5. Создать Use Cases `RegisterDeviceToken` и `UnregisterDeviceToken`.
6. Реализовать FormRequests (`RegisterDeviceTokenRequest`, `UnregisterDeviceTokenRequest`), контроллер `DeviceTokensController` и подключить маршруты в `Presentation/routes.php`.
7. Написать Feature-тесты `DeviceTokensEndpointTest`.

## Окружение: важное про Docker и Git

- Из песочницы агента сокет Docker не виден и `.git` открыт только на чтение: команды `docker compose ...` и `git commit` запускать с `BypassSandbox: true`.

## Окружение и команды

| Действие | Команда |
|---|---|
| Запуск окружения | `docker compose up -d` |
| Стиль | `composer lint` |
| Статический анализ | `composer analyse` |
| Границы модулей | `composer deps` |
| Тесты | `docker compose exec -T app php artisan test --compact` |

## Важные факты, которые легко забыть

- Платежей нет, только `PaymentMark`.
- `Notifications` слушает доменные события других модулей и не экспортирует зависимости в ядро.
- Задачи очередей и консольные команды идемпотентны (проверка через `alreadySent` в `notifications_logs`).
