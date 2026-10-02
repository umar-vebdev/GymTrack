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
