# GymTrack

Система учёта для фитнес-клубов (Business и Client). Бэкенд написан на Laravel.

## Запуск проекта локально (через Docker Compose)

Проект использует контейнеры для PHP-FPM, Nginx, PostgreSQL и Redis.

### Требования
- Docker
- Docker Compose

### Шаги установки

1. Скопируйте конфигурационный файл (если ещё не создан):
   ```bash
   cp .env.example .env
   ```

2. Поднимите контейнеры:
   ```bash
   docker compose up -d
   ```

3. Установите зависимости PHP:
   ```bash
   docker compose exec app composer install
   ```

4. Сгенерируйте ключ приложения:
   ```bash
   docker compose exec app php artisan key:generate
   ```

5. Выполните миграции БД:
   ```bash
   docker compose exec app php artisan migrate
   ```

Проект будет доступен по адресу: [http://localhost:8000](http://localhost:8000)

## Архитектура модулей

Проект построен по принципу **Модульного монолита** с элементами чистой архитектуры. Каждый модуль в `app/Modules/` имеет следующую структуру:

```text
НазваниеМодуля/
├── Contracts/       # Публичные интерфейсы и DTO для взаимодействия с другими модулями
├── Domain/          # Бизнес-правила, сущности, Value Object. Без зависимостей от фреймворка
├── Application/     # Use Cases (сценарии использования), слушатели доменных событий
├── Infrastructure/  # Работа с БД (Eloquent модели), миграции, внешние сервисы
└── Presentation/    # HTTP контроллеры, FormRequests, Routes
```

**Правила взаимодействия:**
1. Модуль не может напрямую обращаться к таблицам или внутренним классам другого модуля.
2. Взаимодействие между модулями происходит только через классы в папке `Contracts` или через доменные события.
3. Слои направлены внутрь: `Domain` ничего не знает про другие слои, `Application` знает только про `Domain`, а `Infrastructure` и `Presentation` знают про `Application` и `Domain`.
