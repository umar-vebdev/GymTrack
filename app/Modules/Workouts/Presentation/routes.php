<?php

declare(strict_types=1);

use App\Modules\Workouts\Presentation\ClientExercisesController;
use App\Modules\Workouts\Presentation\PlatformExercisesController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/platform/workouts')
    ->middleware('auth:sanctum')
    ->group(function (): void {
        Route::get('/exercises', [PlatformExercisesController::class, 'index']);
        Route::post('/exercises', [PlatformExercisesController::class, 'store']);
        Route::patch('/exercises/{exercise}', [PlatformExercisesController::class, 'update'])->whereNumber('exercise');
    });

Route::prefix('api/v1/client/workouts')
    ->middleware('auth:sanctum')
    ->group(function (): void {
        Route::get('/exercises', [ClientExercisesController::class, 'index']);
        Route::post('/exercises', [ClientExercisesController::class, 'store']);
        Route::patch('/exercises/{exercise}', [ClientExercisesController::class, 'update'])->whereNumber('exercise');
    });
