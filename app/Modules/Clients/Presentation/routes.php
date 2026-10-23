<?php

declare(strict_types=1);

use App\Modules\Clients\Presentation\CreateClientController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/business')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::post('/clients', CreateClientController::class);
    });
