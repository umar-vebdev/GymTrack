<?php

declare(strict_types=1);

use App\Modules\Trainers\Presentation\CreateTrainerController;
use App\Modules\Trainers\Presentation\ListTrainersController;
use App\Modules\Trainers\Presentation\ShowTrainerController;
use App\Modules\Trainers\Presentation\UpdateTrainerController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/business')
    ->middleware('auth:sanctum')
    ->group(function (): void {
        Route::get('/trainers', ListTrainersController::class);
        Route::post('/trainers', CreateTrainerController::class);
        Route::get('/trainers/{trainer}', ShowTrainerController::class)->whereNumber('trainer');
        Route::patch('/trainers/{trainer}', UpdateTrainerController::class)->whereNumber('trainer');
    });
