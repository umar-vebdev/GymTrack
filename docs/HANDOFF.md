# Handoff — GymTrack

**Обновлено:** 2026-11-13
**Текущий этап:** Этап 6 (`E6-A` завершён; следующий `E6-B` → `E6-C`)
**Текущая задача:** Согласование детального плана `E6-B` (Вариант A)

## Что сделано в последней сессии

- **`T6-01` (часовой пояс зала)**: добавлена миграция `gyms.timezone` (`Asia/Dushanbe` по умолчанию), валидация IANA-пояса в `Gym`, `timezone` в `GymQrSummary` и `GymLookupInterface::findTimezone` (`517 passed, 3438 assertions`).
- **`T6-02` (часовой пояс в `Visits`, `D-007`)**: локальная дата `visit_date` и окно времени `WithinAllowedTimeRule` переведены на часовой пояс зала с сохранением `visited_at` в UTC (`519 passed, 3446 assertions`).
- **`T6-03` (подписка для тренера, `D-012`)**: `EnsureActiveCompanySubscription` учитывает `companyIdForTrainer`, добавлен тест в `ReadOnlyExpiredSubscriptionTest` (`520 passed, 3449 assertions`).
- **`T6-04a` (Use Cases филиалов)**: `ListCompanyGyms`, `CreateGym`, `UpdateGym` и расширение `GymRepositoryInterface` (`522 passed, 3464 assertions`).
- **`T6-04b` (HTTP-эндпоинты филиалов)**: `GymsController` (`GET/POST /api/v1/business/gyms`, `PATCH /api/v1/business/gyms/{gym}`), `docs/api/companies.yaml`, `ManageGymsEndpointTest` (`524 passed, 3492 assertions`).
- **`T6-05a` (Use Cases сотрудников)**: `UserSummary` в `Identity`, `ListStaffMembers`, `AddStaffMember`, `UpdateStaffMemberRole`, `RemoveStaffMember`, аудит-события `StaffMemberAdded`/`RoleChanged`/`Removed`, `StaffUseCasesTest` (`526 passed, 3509 assertions`, `lint`/`analyse`/`deps` зелёные).
- **`T6-05b` (HTTP-эндпоинты сотрудников, `E6-A` завершён)**: `StaffController` (`GET/POST /api/v1/business/staff`, `PATCH/DELETE /api/v1/business/staff/{staff}`), `AddStaffMemberRequest`, `UpdateStaffMemberRoleRequest`, `StaffMemberResource`, `docs/api/companies.yaml`, `README.md`, `ManageStaffEndpointTest` (`528 passed, 3540 assertions`, `lint`/`analyse`/`deps` зелёные).

## Точный следующий шаг

1. Подтвердить с разработчиком детальный список задач эпика `E6-B` (Вариант A: сидеры, локализация `ru`/`tg` + `Accept-Language`, rate limiting на auth/OTP, OpenAPI для Этапов 1–2, JSON-логи и Docker worker/scheduler).
2. После подтверждения приступить к `T6-06`.

## Команды проверки

```bash
composer lint
composer analyse
composer deps
docker compose exec -T app composer test -- --compact --colors=never
```

## Известные проблемы и замечания

- В feature-тестах Laravel кэширует контроллер на экземпляре `Route` в пределах одного теста, поэтому тестовый `Clock` с изменением времени внутри одного теста должен читать время из мутабельного свойства теста.
- В контейнере `app` команда `composer test` выполняется при условии `php artisan config:clear` перед прогоном; запуск через `docker compose exec -T app composer test -- --compact --colors=never`.
- Файл `.deptrac.cache` отслеживается в git и обновляется при запуске `composer deps`.
- Пробелы относительно ТЗ на 2026-11-13 (запланированы в `E6-B`): отсутствует `lang/` (`ru`/`tg` + `Accept-Language`), отсутствуют сидеры библиотеки/тарифов, нет OpenAPI-спецификаций для Этапов 1–2, rate limiting настроен только на `/visits/*`, нет JSON-формата логов и отдельных сервисов `worker`/`scheduler` в `docker-compose.yml`.

## Нерешённые вопросы

- Вектор Этапа 6 выбран: `B -> A -> C` (`E6-A` завершён, ожидается подтверждение списка задач `E6-B`).
