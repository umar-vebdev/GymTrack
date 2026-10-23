# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.
> Цель: новая сессия без истории чата продолжает ровно с этого места. Держать в пределах ~100 строк.

**Обновлено:** (дата и время)
**Этап:** 1 — Business (Аутентификация, пользователи и компании)
**Ветка:** main

## Текущая задача

- Задача: T1-14 Поиск и список клиентов
- Статус: не начата

## Последняя сессия: что сделано

- **T1-13**: контракт `Identity/Contracts/UserQueryInterface` (+ `EloquentUserQuery`), событие `Identity/Contracts/PhoneVerified` (диспатчится в `VerifyPhone`), Listener `Clients/Application/Listeners/LinkUserToClients`, `CreateClient` проставляет `user_id` для подтверждённого телефона. Решение D-002.
- T1-12: эндпоинт `POST /api/v1/business/clients` (готово ранее).

## Точный следующий шаг

1. T1-14: UseCase `SearchClients` (фильтр `search` по имени/телефону, с пагинацией), `SearchClientsController`, `GET /api/v1/business/clients` в `Clients/Presentation/routes.php`; компания — через `StaffCompanyResolverInterface`.
2. Feature-тест: поиск по части телефона + изоляция компаний.

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
