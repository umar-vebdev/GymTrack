<?php

declare(strict_types=1);

namespace App\Modules\Visits;

use App\Modules\Visits\Domain\QrTokenServiceInterface;
use App\Modules\Visits\Domain\VisitRepositoryInterface;
use App\Modules\Visits\Infrastructure\EloquentVisitRepository;
use App\Modules\Visits\Infrastructure\HmacQrTokenService;
use Illuminate\Support\ServiceProvider;

final class VisitsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            QrTokenServiceInterface::class,
            HmacQrTokenService::class,
        );
        $this->app->bind(
            VisitRepositoryInterface::class,
            EloquentVisitRepository::class,
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Infrastructure/Migrations');

        if (file_exists(__DIR__.'/Presentation/routes.php')) {
            $this->loadRoutesFrom(__DIR__.'/Presentation/routes.php');
        }
    }
}
