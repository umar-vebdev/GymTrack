<?php

declare(strict_types=1);

use App\Modules\Trainers\Presentation\CreateTrainerController;
use App\Modules\Trainers\Presentation\UpdateTrainerController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/business')
    ->middleware('auth:sanctum')
    ->group(function (): void {
        Route::post('/trainers', CreateTrainerController::class);
        Route::patch('/trainers/{trainer}', UpdateTrainerController::class)->whereNumber('trainer');
    });
