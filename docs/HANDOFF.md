# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.
> Цель: новая сессия без истории чата продолжает ровно с этого места. Держать в пределах ~100 строк.

**Обновлено:** 2026-11-02
**Этап:** 2 — Client (эпик E2-D Notifications: push и SMS, напоминания об окончании абонемента)
**Ветка:** main

## Текущая задача

- Эпик **E2-D Notifications** раскрыт в задачи `T2-16`..`T2-20` в `docs/TASKS.md`.
- Активная задача: **T2-16** (`[ ]`) — Каркас модуля `Notifications`, журнал `notifications_logs`, шлюзы `PushGatewayInterface`, `NotificationsSmsGatewayInterface` и сервис `NotificationSender`.

## Последняя сессия: что сделано

- Проверены и закрыты все задачи эпика **E2-C** (`T2-11`..`T2-15`): заявки на абонементы, оплата с атомарной выдачей и чеком, автоотмена просроченных заявок.
- Устранены замечания линтера Pint (EOF blank lines в модуле `Sales`, коммит `62ec0e7`).
- Проанализированы разделы Бизнес-ТЗ (5.3, 5.4, 10, 11, 12.1, 12.5) и Технического ТЗ (3.6, 3.7, 4.1, 4.4, 9.4) для модуля `Notifications`.
- Эпик **E2-D Notifications** детализирован на 5 атомарных задач (`T2-16`..`T2-20`) по правилу 4.3 `AGENTS.md`.
- 234 теста (1016 проверок) зелёные, `lint`/`analyse`/`deps` чистые.

## Точный следующий шаг

1. Получить подтверждение разработчика на план эпика **E2-D** и перейти к задаче **T2-16**.
2. В задаче **T2-16**:
   - Создать директории `app/Modules/Notifications/{Domain,Application,Infrastructure,Presentation,Contracts}`.
   - Миграция `notifications_logs` (`id`, `company_id`, `user_id`, `client_id`, `channel`, `type`, `recipient`, `message`, `status`, `sent_at`, `created_at`, `updated_at`).
   - Перечисления `NotificationChannel` и `NotificationType`, интерфейсы `PushGatewayInterface` и `NotificationsSmsGatewayInterface`, адаптеры `LogPushGateway` и `LogNotificationsSmsGateway`.
   - Сервис `NotificationSender` с записью в БД, регистрация `NotificationsServiceProvider` в `bootstrap/providers.php`.
   - Unit-тесты `NotificationSenderTest`.

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
- `Notifications` не экспортирует зависимости в другие модули (слушает события `VisitRegistered`, `MembershipRequestPaid` или запускается по расписанию).
- В джобы очередей `company_id` передаётся явно, без надежды на HTTP-контекст тенанта.
- Все задачи очередей и команды расписания идемпотентны.
