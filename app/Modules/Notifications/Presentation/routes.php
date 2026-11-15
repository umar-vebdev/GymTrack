<?php

declare(strict_types=1);

use App\Modules\Notifications\Presentation\DeviceTokensController;
use App\Modules\Notifications\Presentation\SendGymAnnouncementController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/business')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::post('/notifications/announcements', SendGymAnnouncementController::class);
    });

Route::prefix('api/v1/client')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::post('/device-tokens', [DeviceTokensController::class, 'register']);
        Route::delete('/device-tokens', [DeviceTokensController::class, 'unregister']);
    });
