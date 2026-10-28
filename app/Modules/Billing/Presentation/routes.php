<?php

declare(strict_types=1);

use App\Modules\Billing\Presentation\ActivateCompanySubscriptionController;
use App\Modules\Billing\Presentation\GetCompanySubscriptionController;
use App\Modules\Billing\Presentation\ListPlatformPlansController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/business/billing')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::get('/plans', ListPlatformPlansController::class);
        Route::get('/subscription', GetCompanySubscriptionController::class);
    });

Route::prefix('api/v1/platform')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::post('/companies/{id}/subscription/activate', ActivateCompanySubscriptionController::class);
    });

