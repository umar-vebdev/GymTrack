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
- **T2-07** `[~]` Контракт списания абонемента (`MembershipVisitConsumerInterface`) и цепочка правил `CheckInRule`.
- Следующие задачи эпика E2-B: **T2-08**..**T2-10**.

## Последняя сессия: что сделано

- **E1-H Analytics** (`T1-41`…`T1-43`) и **E2-A Регистрация клиента** (`T2-01`…`T2-03`) закрыты.
- **T2-04** закрыта: зашифрованный `qr_secret` в `gyms`, `GymQrSummary`, `GymQrLookupInterface`.
- **T2-05** закрыта: `QrPayload`, `HmacQrTokenService`, `GenerateGymQr`, `GET /api/v1/business/gyms/{id}/qr`.
- **T2-06** закрыта: `Visit`, `VisitMethod`, `VisitStatus`, `VisitRegistered`, миграция `visits_visits` с защитой от дубля за день, `EloquentVisitRepository` и `VisitRepositoryTest`.
- Всего 175 тестов (741 проверка) зелёные.

## Точный следующий шаг

1. Выполнить **T2-07** (`MembershipVisitConsumerInterface` в `Memberships\Contracts` и цепочка правил `CheckInRule` в `Visits\Domain\Rules`).
2. Выполнить **T2-08** (`CheckInByQr` и `POST /api/v1/client/visits/check-in`).

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
