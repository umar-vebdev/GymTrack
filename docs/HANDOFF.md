# HANDOFF — состояние проекта для продолжения работы

> Файл **перезаписывается** целиком, а не дописывается. Обновлять после каждой задачи.

**Обновлено:** 2026-11-09
**Этап:** 4 — Расписание и тренеры (E4-A в работе)
**Ветка:** main

## Текущее состояние

- Этапы 0–3 завершены и приняты. Эпик **E4-A (Тренеры) завершён** (`T4-01`…`T4-04`).
- Эпик **E4-B (Расписание)** в работе: `T4-05a`, `T4-05b`, `T4-06a`, `T4-06b` завершены, следующая задача `T4-06c`.

## Последняя сессия

- **T4-01…T4-04 [x]**: эпик E4-A завершён (роль `Role::TRAINER` по D-012, модуль `Trainers`, CRUD и просмотр тренеров зала).
- **T4-05a [x]**: контракт `TrainerLookupInterface::existsActiveInGym` в `Trainers/Contracts` + `EloquentTrainerLookup`; сущность `ClassTemplate` и `InvalidClassTemplate` в `Schedule/Domain`, 3 теста.
- **T4-05b [x]**: миграция `schedule_class_templates`, `ClassTemplateRepositoryInterface` + `EloquentClassTemplateRepository` (`ClassTemplateModel`), `ScheduleServiceProvider` зарегистрирован в `bootstrap/providers.php`, 2 теста в `ClassTemplateRepositoryTest`.
- **T4-06a [x]**: сценарии `CreateClassTemplate`, `UpdateClassTemplate`, `ListClassTemplates` (`CreateClassTemplateCommand`, `UpdateClassTemplateCommand`, `ScheduleResourceNotFound`), 3 теста в `ClassTemplateUseCasesTest`.
- **T4-06b [x]**: эндпоинты `POST /api/v1/business/schedule/templates` и `PATCH /api/v1/business/schedule/templates/{template}` (`CreateClassTemplateController`, `UpdateClassTemplateController`, `StoreClassTemplateRequest`, `UpdateClassTemplateRequest`, `ClassTemplateResource`, `routes.php`), 3 теста в `ManageClassTemplatesTest`.
- **456 тестов, 2514 проверок**; `composer lint`, `composer analyse`, `composer test`, `composer deps` проходят.

## Текущая задача и точный следующий шаг

1. В работе **T4-06c [~]**: эндпоинт списка шаблонов расписания зала `GET /api/v1/business/schedule/templates?gym_id={id}&only_active=1` (`ListClassTemplatesRequest`, `ListClassTemplatesController`), маршрут и тесты в `ManageClassTemplatesTest`.
2. После закрытия `T4-06c` перейти к **T4-07**: миграция `schedule_sessions`, сущность `ClassSession`, репозиторий и идемпотентный сценарий `GenerateClassSessions`.

## Ранее реализовано: фотографии

- `POST /api/v1/business/gyms/{gym}/photos`: jpeg/png/webp до 5 МБ, максимум 10; только владелец, pending запрещён, изменение → draft.
- `DELETE /api/v1/business/gyms/{gym}/photos/{photo}`: общий с загрузкой lock строки зала, проверка компании/зала/фото, сброс модерации; успех 200 с `data: null`.
- Удаление восстанавливает файл при перехваченном сбое; аварийное завершение процесса не покрывается. Отсутствующий файл не мешает удалить запись (D-009, допущение ждёт подтверждения).
- OpenAPI: `docs/api/gym-photos.yaml`, `docs/api/gym-profiles.yaml`, `docs/api/public-catalog.yaml`.

## Окружение: важное про Docker и Git

- Из песочницы сокет Docker недоступен, `.git` доступен только для чтения: Docker и запись git требуют `BypassSandbox: true`. PHPStan вне песочницы нужен локальный сокет рабочих процессов; использовать тот же режим.

## Команды

| Действие | Команда |
|---|---|
| Запуск окружения | `docker compose up -d` |
| Стиль / анализ / границы | `composer lint` / `composer analyse` / `composer deps` |
| Тесты | `docker compose exec -T app composer test -- --compact --colors=never` |

## Важные факты

- Платежей нет, только `PaymentMark`.
- У одного аккаунта (`users.id`) может быть несколько карточек клиента в разных залах.
- Каталог публичный (`/api/v1/public/...`), модерация через `/api/v1/platform/...`.
- Витрина — таблица `companies_gym_profiles` (1:1 с `gyms`), в модуле Companies.
