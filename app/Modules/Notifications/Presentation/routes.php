<?php

declare(strict_types=1);

use App\Modules\Notifications\Presentation\DeviceTokensController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/client')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::post('/device-tokens', [DeviceTokensController::class, 'register']);
        Route::delete('/device-tokens', [DeviceTokensController::class, 'unregister']);
    });
