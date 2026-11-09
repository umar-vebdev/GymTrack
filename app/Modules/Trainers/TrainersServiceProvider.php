<?php

declare(strict_types=1);

namespace App\Modules\Trainers;

use App\Modules\Trainers\Domain\TrainerRepositoryInterface;
use App\Modules\Trainers\Infrastructure\EloquentTrainerRepository;
use Illuminate\Support\ServiceProvider;

final class TrainersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TrainerRepositoryInterface::class, EloquentTrainerRepository::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Infrastructure/Migrations');

        if (file_exists(__DIR__.'/Presentation/routes.php')) {
            $this->loadRoutesFrom(__DIR__.'/Presentation/routes.php');
        }
    }
}
