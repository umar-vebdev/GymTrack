# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.
> Цель: новая сессия без истории чата продолжает ровно с этого места. Держать в пределах ~100 строк.

**Обновлено:** (дата и время)
**Этап:** 1 — Business (Аутентификация, пользователи и компании)
**Ветка:** main

## Текущая задача

- Задача: T1-02 Регистрация пользователя
- Статус: не начата

## Последняя сессия: что сделано

- Настроена таблица `users` и модель `User` (добавлены `phone`, `is_phone_verified`).
- Создан доменный слой для пользователей (`App\Modules\Identity\Domain\User` и `UserRepositoryInterface`).
- Реализован `EloquentUserRepository` в слое Infrastructure и привязан в `IdentityServiceProvider` (T1-01 выполнена).

## Точный следующий шаг

1. Начать T1-02: Создать класс-сценарий (UseCase) `RegisterUser` в слое Application. Он должен принимать DTO/данные, проверять уникальность (или полагаться на БД/Request), хэшировать пароль (`Hash::make`) и сохранять пользователя через `UserRepositoryInterface`.
2. Настроить API-контроллер `RegisterUserController` (`POST /api/v1/auth/register`), который использует `FormRequest` для валидации (`phone`, `email`, `password`) и вызывает UseCase.
3. Добавить маршрут в `routes/api.php` или `Identity/Presentation/routes.php`.
4. Написать Feature-тест регистрации (`tests/Feature/Identity/RegisterUserTest.php`), проверяющий статусы ответа и сохранение в БД.

## Окружение и команды

| Действие | Команда |
|---|---|
| Запуск окружения | `docker compose up -d` |
| Стиль | `composer lint` |
| Статический анализ | `composer analyse` |
| Границы модулей | `composer deps` |
| Тесты | `composer test` |

## Важные факты, которые легко забыть

- Платежей нет, только `PaymentMark`.
- Новая функция = новый модуль или расширение через Contracts и события.
- Допущения и принятые решения: см. `docs/DECISIONS.md`.
