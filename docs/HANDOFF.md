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
- **T2-08** `[ ]` — следующая: `CheckInByQr` + `POST /api/v1/client/visits/check-in`.
- Дальше в эпике E2-B: **T2-09**, **T2-10**.

## Последняя сессия: что сделано

- **E1-H Analytics** (`T1-41`…`T1-43`) и **E2-A Регистрация клиента** (`T2-01`…`T2-03`) закрыты.
- **T2-04** закрыта: зашифрованный `qr_secret` в `gyms`, `GymQrSummary`, `GymQrLookupInterface`.
- **T2-05** закрыта: `QrPayload`, `HmacQrTokenService`, `GenerateGymQr`, `GET /api/v1/business/gyms/{id}/qr`.
- **T2-06** закрыта: `Visit`, `VisitMethod`, `VisitStatus`, `VisitRegistered`, миграция `visits_visits` с защитой от дубля за день, `EloquentVisitRepository` и `VisitRepositoryTest`.
- **T2-07** закрыта. Всего 186 тестов (766 проверок) зелёные, lint/analyse/deps чистые.

## Точный следующий шаг

1. **T2-08**: Use Case `CheckInByQr` (проверка QR через `QrTokenServiceInterface::verifyForGym($token, $gymId, $secret, $at)` и `GymQrLookupInterface`; `findCandidate` → `CheckInContext` → цепочка правил → `consumeVisit` → `VisitRepository::create` → событие `VisitRegistered($visitId, $companyId, $gymId, $clientId, $membershipId, $method, $visitedAt, $registeredByUserId, $device)`), всё в одной транзакции с `Idempotency-Key`. Клиентский маршрут вне группы `business`; как определить `clientId` из пользователя — сверить со схемой `clients`/`users` (не угадывать). Правила регистрировать в `VisitsServiceProvider`.
2. Подтвердить у разработчика допущения D-005 и D-006.

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
