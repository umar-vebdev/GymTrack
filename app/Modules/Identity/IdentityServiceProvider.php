<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Modules\Identity\Contracts\SmsGateway;
use App\Modules\Identity\Contracts\TokenGenerator;
use App\Modules\Identity\Domain\UserRepositoryInterface;
use App\Modules\Identity\Infrastructure\EloquentUserRepository;
use App\Modules\Identity\Infrastructure\LogSmsGateway;
use App\Modules\Identity\Infrastructure\SanctumTokenGenerator;
use Illuminate\Support\ServiceProvider;

final class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            UserRepositoryInterface::class,
            EloquentUserRepository::class
        );

        $this->app->bind(
            SmsGateway::class,
            LogSmsGateway::class
        );

        $this->app->bind(
            TokenGenerator::class,
            SanctumTokenGenerator::class
        );
    }

    public function boot(): void
    {
        // Загрузка маршрутов, миграций, фабрик модуля
        $this->loadMigrationsFrom(__DIR__.'/Infrastructure/Migrations');

        if (file_exists(__DIR__.'/Presentation/routes.php')) {
            $this->loadRoutesFrom(__DIR__.'/Presentation/routes.php');
        }
    }
}
