<?php

declare(strict_types=1);

use App\Modules\Visits\Presentation\GetGymQrController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/business')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::get('/gyms/{id}/qr', GetGymQrController::class);
    });
