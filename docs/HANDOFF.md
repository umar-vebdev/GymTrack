# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.
> Цель: новая сессия без истории чата продолжает ровно с этого места. Держать в пределах ~100 строк.

**Обновлено:** (дата и время)
**Этап:** 1 — Business (Аутентификация, пользователи и компании)
**Ветка:** main

## Текущая задача

- Задача: T1-13 Механизм связывания с пользователем
- Статус: не начата

## Последняя сессия: что сделано

- Завершена **T1-12**: `CreateClient` (UseCase), `CreateClientController`, `CreateClientRequest`, маршрут `POST /api/v1/business/clients`, `existsByPhone` в репозитории, контракт `Companies/Contracts/StaffCompanyResolverInterface` (+ `EloquentStaffCompanyResolver`).
- Дубликат телефона в компании → 409 `CLIENT_PHONE_EXISTS`; не сотрудник → 403.
- Отдельным коммитом исправлен стиль `app/Models/User.php` (Pint).

## Точный следующий шаг

1. T1-13: контракт `Identity/Contracts/UserQueryInterface` (поиск пользователя по телефону), инжектить в `CreateClient` (заполнять `user_id`; `ClientRepositoryInterface::create` уже принимает `userId`).
2. Событие `UserRegistered` в Identity + Listener `LinkUserToClients` в Clients.
3. Тесты двустороннего связывания.

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
