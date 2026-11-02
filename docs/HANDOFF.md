# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.
> Цель: новая сессия без истории чата продолжает ровно с этого места. Держать в пределах ~100 строк.

**Обновлено:** 2026-11-02
**Этап:** 2 — Client (эпик E2-D Notifications: push и SMS, напоминания об окончании абонемента)
**Ветка:** main

## Текущая задача

- **T2-17** (`[x]`): регистрация и отзыв push-токенов клиентских устройств (`POST /api/v1/client/device-tokens`, `DELETE /api/v1/client/device-tokens`).
- Следующая активная задача: **T2-18** (`[ ]`) — Очереди и фоновые задачи отправки уведомлений (`SendPushNotificationJob`, `SendSmsNotificationJob` с явным `company_id`).

## Последняя сессия: что сделано

- **T2-16** (`7ed2ae7`): каркас модуля `Notifications`, миграция `notifications_logs`, интерфейсы шлюзов `PushGatewayInterface`, `SmsGatewayInterface`, адаптеры `LogPushGateway`, `LogNotificationsSmsGateway`, репозиторий `EloquentNotificationLogRepository` и сервис `NotificationSender`.
- **T2-17**:
  - Миграция `notifications_device_tokens` с уникальным индексом `(user_id, token)`.
  - Модель `DeviceTokenModel`, интерфейс `DeviceTokenRepositoryInterface` и репозиторий `EloquentDeviceTokenRepository`.
  - Сценарии `RegisterDeviceToken` и `UnregisterDeviceToken`.
  - Запросы валидации `RegisterDeviceTokenRequest`, `UnregisterDeviceTokenRequest`, контроллер `DeviceTokensController` и клиентские маршруты под `auth:sanctum`.
  - Feature-тесты `DeviceTokensEndpointTest` (7 проверок: неавторизованный доступ, успешная регистрация, идемпотентный upsert, валидация 422, удаление токена, изоляция между пользователями).
- Всего 246 тестов (1073 проверки) зелёные, `lint`/`analyse`/`deps` чистые.

## Точный следующий шаг

1. Перечитать раздел Технического ТЗ 3.6 и 9.4 для задачи **T2-18**.
2. Поставить статус `[~]` для **T2-18** в `docs/TASKS.md` и обновить `docs/HANDOFF.md`.
3. Создать джобы `SendPushNotificationJob` и `SendSmsNotificationJob` (интерфейс `ShouldQueue`, явный `companyId`, отправка через `NotificationSender`).
4. Написать Unit-тесты `NotificationJobsTest` на успешное выполнение джоб и обработку ошибок.

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
- Задачи очередей получают `company_id` явно (Тех. ТЗ 9.4).
- Все операции идемпотентны.
