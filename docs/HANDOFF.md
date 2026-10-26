# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.
> Цель: новая сессия без истории чата продолжает ровно с этого места. Держать в пределах ~100 строк.

**Обновлено:** 2026-10-26
**Этап:** 1 — Business (E1-E Memberships)
**Ветка:** main

## Текущая задача

- Задача: T1-27b Ежедневное завершение истёкших абонементов (`ExpireDueMemberships`, команда `memberships:expire-due`, расписание)
- Статус: в работе (`[~]`)
- **НАПОМИНАНИЕ (требование разработчика):** после реализации E1-F (Sales/платежи) дополнить блок Memberships (выдача вместе с `PaymentMark`, возвраты). Записано в `TASKS.md` в E1-E.

## Последняя сессия: что сделано

- **T1-27a** закрыта: список абонементов клиента в зале (`GET /api/v1/business/clients/{id}/memberships`) + Feature-тесты. Всего 131 тест зелёный.

## Точный следующий шаг

1. T1-27b: добавить в `MembershipRepositoryInterface` методы `listDueForExpiry(int $companyId, DateTimeImmutable $at): array` и `listCompanyIdsWithDueMemberships(DateTimeImmutable $at): array`, реализовать Use Case `ExpireDueMemberships` (принимает явно `int $companyId`, вызывает `Membership::expireIfDue($now)` и `save`, идемпотентен), создать консольную команду `ExpireDueMembershipsCommand` (`memberships:expire-due`, опция `--company=`), зарегистрировать в `MembershipsServiceProvider` и ежедневное расписание в `routes/console.php`, покрыть тестами.

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
