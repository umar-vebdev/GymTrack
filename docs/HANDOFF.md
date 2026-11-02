# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.
> Цель: новая сессия без истории чата продолжает ровно с этого места. Держать в пределах ~100 строк.

**Обновлено:** 2026-11-02
**Этап:** 2 — Client (эпик E2-D Notifications: push и SMS, напоминания об окончании абонемента)
**Ветка:** main

## Текущая задача

- **T2-18** (`[x]`): очереди и фоновые задачи отправки уведомлений (`SendPushNotificationJob`, `SendSmsNotificationJob` с явным `company_id`).
- Следующая активная задача: **T2-19** (`[ ]`) — Слушатели доменных событий: подтверждение визита (`VisitRegistered`) и оплата заявки (`MembershipRequestPaid`), контракт `ClientRecipientDto` в `Clients`.

## Последняя сессия: что сделано

- **T2-16** (`7ed2ae7`): каркас модуля `Notifications`, миграция `notifications_logs`, интерфейсы шлюзов `PushGatewayInterface`, `SmsGatewayInterface`, сервис `NotificationSender`.
- **T2-17** (`9faf136`): регистрация и отзыв push-токенов (`POST/DELETE /api/v1/client/device-tokens`), таблица `notifications_device_tokens`, Feature-тесты.
- **T2-18**:
  - Созданы фоновые джобы `SendPushNotificationJob` и `SendSmsNotificationJob` с интерфейсом `ShouldQueue` и трейтом `Queueable`.
  - В обе джобы `company_id` передаётся явно, сохраняя мультиарендность без HTTP-контекста (Тех. ТЗ 9.4).
  - `SendPushNotificationJob` поддерживает прямую отправку по токену либо автоматический поиск всех активных токенов пользователя через `DeviceTokenRepositoryInterface`.
  - Unit-тесты `NotificationJobsTest` (5 тестов: вызов SMS-сендера, прямой push, отправка на несколько токенов пользователя, пропуск при отсутствии токенов, постановка в очередь через `Queue::fake()`).
- Всего 251 тест (1092 проверки) зелёные, `lint`/`analyse`/`deps` чистые.

## Точный следующий шаг

1. Перечитать раздел Бизнес-ТЗ 5.3, 5.4 и Технического ТЗ 3.5, 3.7 для задачи **T2-19**.
2. Поставить статус `[~]` для **T2-19** в `docs/TASKS.md` и обновить `docs/HANDOFF.md`.
3. Создать DTO `ClientRecipientDto` в `Clients/Contracts` и добавить метод `findRecipient(int $clientId): ?ClientRecipientDto` в `ClientLookupInterface`.
4. Реализовать `findRecipient` в `EloquentClientLookup`.
5. Создать слушатели событий `SendVisitNotificationListener` (на `VisitRegistered`) и `SendMembershipRequestPaidNotificationListener` (на `MembershipRequestPaid`).
6. Зарегистрировать слушатели в `NotificationsServiceProvider`.
7. Написать Feature-тесты `NotificationEventListenersTest`.

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
- `Notifications` слушает события `VisitRegistered` и `MembershipRequestPaid` других модулей.
- Задачи очередей получают `company_id` явно.
