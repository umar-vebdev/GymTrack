<?php

declare(strict_types=1);

namespace App\Modules\Workouts;

use App\Modules\Workouts\Domain\ExerciseRepositoryInterface;
use App\Modules\Workouts\Domain\WorkoutPlanRepositoryInterface;
use App\Modules\Workouts\Domain\WorkoutSessionRepositoryInterface;
use App\Modules\Workouts\Infrastructure\EloquentExerciseRepository;
use App\Modules\Workouts\Infrastructure\EloquentWorkoutPlanRepository;
use App\Modules\Workouts\Infrastructure\EloquentWorkoutSessionRepository;
use Illuminate\Support\ServiceProvider;

final class WorkoutsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ExerciseRepositoryInterface::class, EloquentExerciseRepository::class);
        $this->app->bind(WorkoutPlanRepositoryInterface::class, EloquentWorkoutPlanRepository::class);
        $this->app->bind(WorkoutSessionRepositoryInterface::class, EloquentWorkoutSessionRepository::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Infrastructure/Migrations');

        if (file_exists(__DIR__.'/Presentation/routes.php')) {
            $this->loadRoutesFrom(__DIR__.'/Presentation/routes.php');
        }
    }
}
