# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.
> Цель: новая сессия без истории чата продолжает ровно с этого места. Держать в пределах ~100 строк.

**Обновлено:** 2026-10-30
**Этап:** 2 — Client (переход к E2-B Visits)
**Ветка:** main

## Текущая задача

- **T2-04** `[x]` Секретный ключ QR зала (`qr_secret`) и контракт `GymQrLookupInterface` в `Companies`.
- **T2-05** `[x]` Динамический QR-токен (`HmacQrTokenService`) и эндпоинт ресепшена `GET /api/v1/business/gyms/{id}/qr`.
- **T2-06** `[x]` Домен и инфраструктура посещений (`Visit`, `visits_visits`, `EloquentVisitRepository`).
- **T2-07** `[x]` Контракт списания (`MembershipVisitConsumerInterface`, реализация `ConsumeMembershipVisit`, `Membership::restoreVisit`) и 4 правила `CheckInRule`.
- **T2-08** `[x]` Сценарий отметки по QR (`CheckInByQr`) и эндпоинт `POST /api/v1/client/visits/check-in`.
- **T2-09** `[~]` — в работе (`T2-09a` `[~]`, `T2-09b` `[ ]`): ручная отметка администратором и отмена в течение 24 ч.
- Дальше в эпике E2-B: **T2-10**.

## Последняя сессия: что сделано

- **E1-H Analytics** (`T1-41`…`T1-43`) и **E2-A Регистрация клиента** (`T2-01`…`T2-03`) закрыты.
- **T2-04** закрыта: зашифрованный `qr_secret` в `gyms`, `GymQrSummary`, `GymQrLookupInterface`.
- **T2-05** закрыта: `QrPayload`, `HmacQrTokenService`, `GenerateGymQr`, `GET /api/v1/business/gyms/{id}/qr`.
- **T2-06** закрыта: `Visit`, `VisitMethod`, `VisitStatus`, `VisitRegistered`, миграция `visits_visits` с защитой от дубля за день, `EloquentVisitRepository` и `VisitRepositoryTest`.
- **T2-07**, **T2-08** (`T2-08a`..`T2-08c`) закрыты. Всего 197 тестов (809 проверок) зелёные, lint/analyse/deps чистые.

## Точный следующий шаг

1. **T2-09a** — Ручная отметка посещения сотрудником зала: `CheckInManually` (`CheckInManuallyCommand`), `ManualCheckInRequest`, `ManualCheckInController`, `POST /api/v1/business/clients/{id}/visits` (с поддержкой `visited_at` задним числом и `Idempotency-Key`, middleware `company.subscription.active`).
2. **T2-09b** — Отмена ошибочной отметки в течение 24 ч (`POST /api/v1/business/visits/{id}/cancel`, `VisitCancelled` событие аудита, возврат занятия через `restoreVisit()`).
   - Факты для T2-08b: `QrTokenServiceInterface::verifyForGym($token, $gymId, $secret, $at)`; `GymQrLookupInterface::findById($gymId)` → `GymQrSummary` (`companyId`, `qrSecret`); `VisitRegistered($visitId, $companyId, $gymId, $clientId, $membershipId, $method, $visitedAt, $registeredByUserId, $device)`; правила — `CheckInRuleInterface::validate(CheckInContext)`; уникальный индекс `(client_id, gym_id, visit_date, daily_entry_number)` для `completed`; транзакцию делать через `DB::transaction`, событие слать после коммита (образец — `RegisterSale`).
   - Токен QR сейчас не содержит `gymId` отдельно от строки `gymId|slot|signature`; `QrPayload::parseTokenString` парсит его.
2. Допущения D-005/D-006 отменены решением D-007 (лимит и окно времени — из абонемента); подтверждения не требуются.

## Окружение: важное про Docker

- Из песочницы агента сокет Docker не виден: команды `docker compose ...` запускать с обходом песочницы (нужно подтверждение разработчика).

## Окружение и команды

| Действие | Команда |
|---|---|
| Запуск окружения | `docker compose up -d` |
| Стиль | `composer lint` |
| Статический анализ | `composer analyse` |
| Границы модулей | `composer deps` |
| Тесты | `docker compose exec -T app composer test` (на хосте нет pgsql) |

## Важные факты, которые легко забыть

- Платежей нет, только `PaymentMark`.
- Новая функция = новый модуль или расширение через Contracts и события.
