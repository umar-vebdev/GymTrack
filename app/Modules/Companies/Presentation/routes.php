<?php

declare(strict_types=1);

use App\Modules\Companies\Presentation\DeleteGymPhotoController;
use App\Modules\Companies\Presentation\ListCurrenciesController;
use App\Modules\Companies\Presentation\ListGymProfilesController;
use App\Modules\Companies\Presentation\ModerateGymProfileController;
use App\Modules\Companies\Presentation\RegisterCompanyController;
use App\Modules\Companies\Presentation\SetGymPublicationController;
use App\Modules\Companies\Presentation\SubmitGymProfileController;
use App\Modules\Companies\Presentation\UpdateCompanyCurrencyController;
use App\Modules\Companies\Presentation\UpdateGymProfileController;
use App\Modules\Companies\Presentation\UploadGymPhotoController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/business')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::post('/companies', RegisterCompanyController::class);
        Route::get('/currencies', ListCurrenciesController::class);
        Route::patch('/company/currency', UpdateCompanyCurrencyController::class);
        Route::put('/gyms/{gym}/profile', UpdateGymProfileController::class)->whereNumber('gym');
        Route::patch('/gyms/{gym}/publication', SetGymPublicationController::class)->whereNumber('gym');
        Route::post('/gyms/{gym}/profile/submit', SubmitGymProfileController::class)->whereNumber('gym');
        Route::post('/gyms/{gym}/photos', UploadGymPhotoController::class)->whereNumber('gym');
        Route::delete('/gyms/{gym}/photos/{photo}', DeleteGymPhotoController::class)->whereNumber(['gym', 'photo']);
    });

Route::prefix('api/v1/platform')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::get('/gym-profiles', ListGymProfilesController::class);
        Route::post('/gyms/{gym}/moderate', ModerateGymProfileController::class)->whereNumber('gym');
    });
