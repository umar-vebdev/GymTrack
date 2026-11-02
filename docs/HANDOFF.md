# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.
> Цель: новая сессия без истории чата продолжает ровно с этого места. Держать в пределах ~100 строк.

**Обновлено:** 2026-11-02
**Этап:** 2 — Client (завершён эпик E2-D Notifications; переход к E2-E API для приложения клиента: мои абонементы, история)
**Ветка:** main

## Текущая задача

- **E2-D Notifications: push и SMS, напоминания об окончании абонемента** (`T2-16`..`T2-20`) `[x]` — полностью закрыт.
- Следующий и финальный эпик этапа 2: **E2-E API для приложения клиента: мои абонементы, история** (`[ ]` — перечитать разделы ТЗ, раскрыть в мелкие задачи и согласовать с разработчиком по правилу 4.3 `AGENTS.md`).

## Последняя сессия: что сделано

- **T2-16** (`7ed2ae7`): модуль `Notifications`, таблица `notifications_logs`, шлюзы `PushGatewayInterface`, `SmsGatewayInterface` с адаптерами `LogPushGateway` и `LogNotificationsSmsGateway`, репозиторий `EloquentNotificationLogRepository` и сервис `NotificationSender`.
- **T2-17** (`9faf136`): регистрация и отзыв push-токенов (`POST/DELETE /api/v1/client/device-tokens`), таблица `notifications_device_tokens`, Feature-тесты.
- **T2-18** (`93e6372`): очереди и фоновые задачи `SendPushNotificationJob`, `SendSmsNotificationJob` с явной передачей `company_id`.
- **T2-19** (`0e645b3`): DTO `ClientRecipientDto` и метод `findRecipient` в `ClientLookupInterface`, геттеры в `VisitRegistered`, слушатели `SendVisitNotificationListener` и `SendMembershipRequestPaidNotificationListener`, Feature-тесты.
- **T2-20**:
  - DTO `ExpiringMembershipDto` и метод `findExpiringBetween` в `MembershipLookupInterface`, `FindUsableMembership` и `EloquentMembershipRepository`.
  - Защита от дублей (идемпотентность) в `SendPushNotificationJob` и `SendSmsNotificationJob` через `alreadySent`.
  - Сценарий `SendExpiringMembershipReminders` (напоминания за 7 и 1 день с push/SMS и проверкой идемпотентности).
  - Консольная команда `notifications:send-expiring-reminders` и ежедневное расписание в `routes/console.php`.
  - Feature-тесты `SendExpiringMembershipRemindersTest` (259 тестов, 1113 проверок — все зелёные, `lint`/`analyse`/`deps` чистые).

## Точный следующий шаг

1. Перечитать раздел Бизнес-ТЗ (5.2 «Мои абонементы и история») и Технического ТЗ (7.1 маршруты клиента) для эпика **E2-E**.
2. Детализировать эпик **E2-E** в `docs/TASKS.md` на мелкие задачи и показать список разработчику на подтверждение (правило 4.3 `AGENTS.md`).

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
- `Notifications` подписан на доменные события других модулей и не экспортирует зависимости в ядро.
- Задачи очередей и команды расписания идемпотентны.
