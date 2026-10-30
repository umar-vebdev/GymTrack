# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.
> Цель: новая сессия без истории чата продолжает ровно с этого места. Держать в пределах ~100 строк.

**Обновлено:** 2026-10-30
**Этап:** 2 — Client (переход к E2-B Visits)
**Ветка:** main

## Текущая задача

- **T2-04** `[x]` Секретный ключ QR зала (`qr_secret`) и контракт `GymQrLookupInterface` в `Companies`.
- **T2-05** `[x]` Динамический QR-токен (`HmacQrTokenService`) и эндпоинт ресепшена `GET /api/v1/business/gyms/{id}/qr`.
- **T2-06** `[~]` Домен и инфраструктура посещений (`Visit`, `visits_visits`, `EloquentVisitRepository`).
- Следующие задачи эпика E2-B: **T2-07**..**T2-10**.

## Последняя сессия: что сделано

- **E1-H Analytics** (`T1-41`…`T1-43`) и **E2-A Регистрация клиента** (`T2-01`…`T2-03`) закрыты.
- **T2-04** закрыта: зашифрованный `qr_secret` в `gyms`, `GymQrSummary`, `GymQrLookupInterface`.
- **T2-05** закрыта: реализованы `QrPayload`, `HmacQrTokenService` (интервал 60 с, HMAC-SHA256, `hash_equals`, приём текущего и предыдущего слота — критерий 12.3), Use Case `GenerateGymQr`, эндпоинт `GET /api/v1/business/gyms/{id}/qr` и тесты `HmacQrTokenServiceTest` и `GymQrEndpointTest`.
- Всего 174 теста (733 проверки) зелёные.

## Точный следующий шаг

1. Выполнить **T2-06** (`Visit`, `visits_visits`, `EloquentVisitRepository`).
2. Выполнить **T2-07** (`MembershipVisitConsumerInterface` и цепочка правил `CheckInRule`).

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
