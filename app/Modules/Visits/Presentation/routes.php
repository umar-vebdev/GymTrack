<?php

declare(strict_types=1);

use App\Modules\Visits\Presentation\CancelVisitController;
use App\Modules\Visits\Presentation\CheckInByQrController;
use App\Modules\Visits\Presentation\GetGymQrController;
use App\Modules\Visits\Presentation\ManualCheckInController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/business')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::get('/gyms/{id}/qr', GetGymQrController::class);
        Route::post('/clients/{id}/visits', ManualCheckInController::class);
        Route::post('/visits/{id}/cancel', CancelVisitController::class);
    });

Route::prefix('api/v1/client')
    ->middleware(['auth:sanctum', 'throttle:30,1'])
    ->group(function () {
        Route::post('/visits/check-in', CheckInByQrController::class);
    });
