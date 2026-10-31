<?php

declare(strict_types=1);

use App\Modules\Companies\Presentation\ListCurrenciesController;
use App\Modules\Companies\Presentation\RegisterCompanyController;
use App\Modules\Companies\Presentation\UpdateCompanyCurrencyController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/business')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::post('/companies', RegisterCompanyController::class);
        Route::get('/currencies', ListCurrenciesController::class);
        Route::patch('/company/currency', UpdateCompanyCurrencyController::class);
    });
