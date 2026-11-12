<?php

declare(strict_types=1);

use App\Modules\Workouts\Presentation\ClientExercisesController;
use App\Modules\Workouts\Presentation\ClientWorkoutLibraryController;
use App\Modules\Workouts\Presentation\PlatformExercisesController;
use App\Modules\Workouts\Presentation\PlatformWorkoutPlansController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/platform/workouts')
    ->middleware('auth:sanctum')
    ->group(function (): void {
        Route::get('/exercises', [PlatformExercisesController::class, 'index']);
        Route::post('/exercises', [PlatformExercisesController::class, 'store']);
        Route::patch('/exercises/{exercise}', [PlatformExercisesController::class, 'update'])->whereNumber('exercise');

        Route::get('/plans', [PlatformWorkoutPlansController::class, 'index']);
        Route::post('/plans', [PlatformWorkoutPlansController::class, 'store']);
        Route::get('/plans/{plan}', [PlatformWorkoutPlansController::class, 'show'])->whereNumber('plan');
        Route::patch('/plans/{plan}', [PlatformWorkoutPlansController::class, 'update'])->whereNumber('plan');
    });

Route::prefix('api/v1/client/workouts')
    ->middleware('auth:sanctum')
    ->group(function (): void {
        Route::get('/exercises', [ClientExercisesController::class, 'index']);
        Route::post('/exercises', [ClientExercisesController::class, 'store']);
        Route::patch('/exercises/{exercise}', [ClientExercisesController::class, 'update'])->whereNumber('exercise');

        Route::get('/library', [ClientWorkoutLibraryController::class, 'index']);
        Route::get('/library/{plan}', [ClientWorkoutLibraryController::class, 'show'])->whereNumber('plan');
    });
