# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.
> Цель: новая сессия без истории чата продолжает ровно с этого места. Держать в пределах ~100 строк.

**Обновлено:** 2026-10-31
**Этап:** 2 — Client (завершён эпик E2-C Заявки на абонемент; переход к E2-D Notifications)
**Ветка:** main

## Текущая задача

- **E2-C Заявки на абонемент (Sales) и их автоотмена** (`T2-11`..`T2-15`) `[x]` — полностью закрыт.
- Следующий эпик этапа 2: **E2-D Notifications: push и SMS, напоминания об окончании абонемента** (`[ ]` — перечитать разделы ТЗ, раскрыть в мелкие задачи и согласовать с разработчиком по правилу 4.3 `AGENTS.md`).

## Последняя сессия: что сделано

- **T2-11** (`fcd69b1`): миграция `sales_membership_requests`, доменная модель `MembershipRequest` и `MembershipRequestStatus`, `EloquentMembershipRequestRepository` с блокировкой `lockForUpdate`, Unit-тесты.
- **T2-12** (`da3198b`): метод `findOrCreateForUser` в `ClientLookupInterface`, событие `MembershipRequestCreated`, сценарии создания и просмотра заявок клиентом (`POST /api/v1/client/membership-requests` со сроком жизни 24 часа и `Idempotency-Key`, `GET /api/v1/client/membership-requests`), Feature-тесты.
- **T2-13** (`bab6f16`): сценарий `ListCompanyMembershipRequests`, эндпоинт зала `GET /api/v1/business/membership-requests` с фильтром по статусу и мультиарендной изоляцией, Feature-тесты.
- **T2-14** (`c0c040d`): сценарий `PayMembershipRequest`, эндпоинт оплаты `POST /api/v1/business/membership-requests/{id}/pay` с атомарным созданием `PaymentMark` и выпуском абонемента через `MembershipIssuerInterface`, события `PaymentMarked` и `MembershipRequestPaid`, Feature-тесты.
- **T2-15**:
  - Консольная команда `sales:expire-requests` (сценарий `ExpireDueMembershipRequests`), автоматическая отмена просроченных заявок `pending` с `expires_at <= now()`, расписание в `routes/console.php`.
  - Сценарий `CancelMembershipRequest`, эндпоинт `POST /api/v1/business/membership-requests/{id}/cancel` и событие `MembershipRequestCancelled`.
  - Feature-тесты `CancelMembershipRequestTest` и `ExpireDueMembershipRequestsCommandTest`.
- Всего 234 теста (1016 проверок) зелёные, `lint`/`analyse`/`deps` чистые.

## Точный следующий шаг

1. Перечитать разделы Бизнес-ТЗ (7, 10, 11) и Технического ТЗ (3.6, 9.4) по модулю `Notifications` (очереди, драйверы Push/SMS, напоминания об окончании абонементов).
2. Детализировать эпик **E2-D Notifications** в `docs/TASKS.md` и показать список задач разработчику на подтверждение (правило 4.3 `AGENTS.md`).

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
- Новая функция = новый модуль или расширение через `Contracts` и события (без прямых JOIN между таблицами разных модулей).
- Заявка на абонемент живёт 24 часа; при оплате выпускается абонемент и создаётся чек продажи с `PaymentMark`.
