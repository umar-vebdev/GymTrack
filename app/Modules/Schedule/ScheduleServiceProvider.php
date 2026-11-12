<?php

declare(strict_types=1);

namespace App\Modules\Schedule;

use App\Modules\Schedule\Contracts\ScheduleCatalogQueryInterface;
use App\Modules\Schedule\Domain\ClassBookingRepositoryInterface;
use App\Modules\Schedule\Domain\ClassSessionRepositoryInterface;
use App\Modules\Schedule\Domain\ClassTemplateRepositoryInterface;
use App\Modules\Schedule\Infrastructure\EloquentClassBookingRepository;
use App\Modules\Schedule\Infrastructure\EloquentClassSessionRepository;
use App\Modules\Schedule\Infrastructure\EloquentClassTemplateRepository;
use App\Modules\Schedule\Infrastructure\EloquentScheduleCatalogQuery;
use Illuminate\Support\ServiceProvider;

final class ScheduleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ClassTemplateRepositoryInterface::class, EloquentClassTemplateRepository::class);
        $this->app->bind(ClassSessionRepositoryInterface::class, EloquentClassSessionRepository::class);
        $this->app->bind(ClassBookingRepositoryInterface::class, EloquentClassBookingRepository::class);
        $this->app->bind(ScheduleCatalogQueryInterface::class, EloquentScheduleCatalogQuery::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Infrastructure/Migrations');

        if (file_exists(__DIR__.'/Presentation/routes.php')) {
            $this->loadRoutesFrom(__DIR__.'/Presentation/routes.php');
        }
    }
}
