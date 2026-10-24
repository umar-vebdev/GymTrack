# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.
> Цель: новая сессия без истории чата продолжает ровно с этого места. Держать в пределах ~100 строк.

**Обновлено:** (дата и время)
**Этап:** 1 — Business (Аутентификация, пользователи и компании)
**Ветка:** main

## Текущая задача

- Задача: T1-25 Заморозка/разморозка: UseCase + эндпоинты `POST /memberships/{id}/freeze|unfreeze`
- Статус: не начата
- **НАПОМИНАНИЕ (требование разработчика):** после реализации E1-F (Sales/платежи) дополнить блок Memberships (выдача вместе с `PaymentMark`, возвраты). Записано в `TASKS.md` в E1-E.

## Последняя сессия: что сделано

- **T1-22, T1-23, T1-24** закрыты. T1-24: контракт `MembershipLookupInterface` + DTO `MembershipSummary`, `FindUsableMembership`, `Membership::isUsableAt`, `MembershipRepositoryInterface::listOpenByClient`. Всего 118 тестов зелёные.

## Точный следующий шаг

1. T1-25: Use Cases `FreezeMembership` и `UnfreezeMembership` (загрузка через `findByIdForUpdate` внутри `DB::transaction`, проверка `companyId`, вызов `Membership::freeze($at)` / `unfreeze($at)`, `save`). Время — через `Shared\Domain\Clock`. Доменные `DomainException` из `Membership` нужно превратить в понятные коды ошибок (409, например `MEMBERSHIP_FREEZE_NOT_ALLOWED`) — лучше ввести специальные исключения вместо голых `DomainException` в `freeze`/`unfreeze`.
2. Эндпоинты `POST /api/v1/business/memberships/{id}/freeze` и `/unfreeze` (владелец и администратор); чужая компания → 404 `MEMBERSHIP_NOT_FOUND`.
3. Feature-тесты: успех, исчерпан лимит, чужая компания, роли.

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
