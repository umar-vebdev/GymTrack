<?php

declare(strict_types=1);

namespace App\Modules\Workouts;

use App\Modules\Workouts\Domain\ExerciseRepositoryInterface;
use App\Modules\Workouts\Infrastructure\EloquentExerciseRepository;
use Illuminate\Support\ServiceProvider;

final class WorkoutsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ExerciseRepositoryInterface::class, EloquentExerciseRepository::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Infrastructure/Migrations');

        if (file_exists(__DIR__.'/Presentation/routes.php')) {
            $this->loadRoutesFrom(__DIR__.'/Presentation/routes.php');
        }
    }
}
