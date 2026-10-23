<?php

declare(strict_types=1);

use App\Modules\Memberships\Presentation\CreateMembershipTypeController;
use App\Modules\Memberships\Presentation\ListMembershipTypesController;
use App\Modules\Memberships\Presentation\SetMembershipTypeActiveController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/business')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::get('/membership-types', ListMembershipTypesController::class);
        Route::post('/membership-types', CreateMembershipTypeController::class);
        Route::patch('/membership-types/{id}/active', SetMembershipTypeActiveController::class);
    });
