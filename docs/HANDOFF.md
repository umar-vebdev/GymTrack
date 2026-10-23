# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.
> Цель: новая сессия без истории чата продолжает ровно с этого места. Держать в пределах ~100 строк.

**Обновлено:** (дата и время)
**Этап:** 1 — Business (Аутентификация, пользователи и компании)
**Ветка:** main

## Текущая задача

- Задача: T1-17 Инфраструктура типов абонементов (миграция `membership_types`, сущность `MembershipType`, репозиторий, `MembershipsServiceProvider`)
- Статус: не начата
- **НАПОМИНАНИЕ (требование разработчика):** после реализации E1-F (Sales/платежи) дополнить блок Memberships (выдача вместе с `PaymentMark`, возвраты). Записано в `TASKS.md` в E1-E.

## Последняя сессия: что сделано

- **T1-16**: `Memberships/Domain`: `MembershipKind`, `StartMode`, `MembershipRules` (VO с инвариантами), `InvalidMembershipRules` + unit-тест (19 кейсов).
- Закрыт эпик E1-D; E1-E раскрыт и подтверждён.

## Точный следующий шаг

1. T1-17: миграция `membership_types` (id, company_id, name, kind, start_mode, price_minor, currency, visits_count, duration_days, validity_days, daily_limit, time_from_minute, time_to_minute, freeze_max_days, freeze_max_times, is_active, timestamps; CHECK цена >= 0), сущность `MembershipType` (id, companyId, name, `Money` price, `MembershipRules`, isActive), `MembershipTypeRepositoryInterface`, Eloquent-реализация (`BelongsToCompany`), `MembershipsServiceProvider` + `bootstrap/providers.php`.
2. Тест репозитория (создать и прочитать тип). Сверить валюту: пока хранить код валюты в строке типа, значение по умолчанию TJS (компания валюту не хранит — решить через `[ASSUMPTION]` в DECISIONS).

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
