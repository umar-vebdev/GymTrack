<?php

declare(strict_types=1);

use App\Modules\Clients\Presentation\CreateClientController;
use App\Modules\Clients\Presentation\SearchClientsController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/business')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::get('/clients', SearchClientsController::class);
        Route::post('/clients', CreateClientController::class);
    });
