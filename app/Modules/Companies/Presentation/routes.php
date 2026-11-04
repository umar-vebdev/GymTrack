<?php

declare(strict_types=1);

use App\Modules\Companies\Presentation\ListCurrenciesController;
use App\Modules\Companies\Presentation\ModerateGymProfileController;
use App\Modules\Companies\Presentation\RegisterCompanyController;
use App\Modules\Companies\Presentation\SubmitGymProfileController;
use App\Modules\Companies\Presentation\UpdateCompanyCurrencyController;
use App\Modules\Companies\Presentation\UpdateGymProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/business')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::post('/companies', RegisterCompanyController::class);
        Route::get('/currencies', ListCurrenciesController::class);
        Route::patch('/company/currency', UpdateCompanyCurrencyController::class);
        Route::put('/gyms/{gym}/profile', UpdateGymProfileController::class)->whereNumber('gym');
        Route::post('/gyms/{gym}/profile/submit', SubmitGymProfileController::class)->whereNumber('gym');
    });

Route::prefix('api/v1/platform')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::post('/gyms/{gym}/moderate', ModerateGymProfileController::class)->whereNumber('gym');
    });
