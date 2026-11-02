# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.
> Цель: новая сессия без истории чата продолжает ровно с этого места. Держать в пределах ~100 строк.

**Обновлено:** 2026-11-02
**Этап:** 2 — Client (эпик E2-D Notifications: push и SMS, напоминания об окончании абонемента)
**Ветка:** main

## Текущая задача

- **T2-19** (`[x]`): слушатели доменных событий: подтверждение визита (`VisitRegistered`) и оплата заявки (`MembershipRequestPaid`), контракт `ClientRecipientDto` в `Clients`.
- Следующая активная задача: **T2-20** (`[ ]`) — Ежедневные напоминания об окончании абонемента за 7 и 1 день (`notifications:send-expiring-reminders`, контракт `MembershipLookupInterface`, расписание в `routes/console.php`).

## Последняя сессия: что сделано

- **T2-16** (`7ed2ae7`): каркас модуля `Notifications`, миграция `notifications_logs`, интерфейсы шлюзов `PushGatewayInterface`, `SmsGatewayInterface`, сервис `NotificationSender`.
- **T2-17** (`9faf136`): регистрация и отзыв push-токенов (`POST/DELETE /api/v1/client/device-tokens`), таблица `notifications_device_tokens`, Feature-тесты.
- **T2-18** (`93e6372`): очереди и фоновые задачи отправки уведомлений (`SendPushNotificationJob`, `SendSmsNotificationJob` с явным `company_id`).
- **T2-19**:
  - DTO `ClientRecipientDto` и метод `findRecipient` в `ClientLookupInterface` и `EloquentClientLookup`.
  - Типизированные геттеры `clientId()`, `gymId()`, `membershipId()` в доменном событии `VisitRegistered`.
  - Слушатели `SendVisitNotificationListener` и `SendMembershipRequestPaidNotificationListener` с регистрацией в `NotificationsServiceProvider`.
  - Feature-тесты `NotificationEventListenersTest` (проверка отправки push при наличии аккаунта, SMS при его отсутствии, push при оплате заявки).
- Всего 255 тестов (1104 проверки) зелёные, `lint`/`analyse`/`deps` чистые.

## Точный следующий шаг

1. Перечитать раздел Бизнес-ТЗ 5.3 и Технического ТЗ 9.4 для задачи **T2-20**.
2. Поставить статус `[~]` для **T2-20** в `docs/TASKS.md` и обновить `docs/HANDOFF.md`.
3. Создать DTO `ExpiringMembershipDto` в `Memberships/Contracts` и добавить метод `findExpiringOnDay(int $daysAhead): array` в `MembershipLookupInterface`.
4. Реализовать `findExpiringOnDay` в `EloquentMembershipRepository` / сервисе.
5. Создать Use Case `SendExpiringMembershipReminders` с проверкой идемпотентности через `alreadySent`.
6. Создать консольную команду `notifications:send-expiring-reminders` и зарегистрировать расписание в `routes/console.php`.
7. Написать Feature-тесты `SendExpiringMembershipRemindersTest`.

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
- Модули общаются только через `Contracts` и доменные события.
- Задачи очередей и команды планировщика идемпотентны.
