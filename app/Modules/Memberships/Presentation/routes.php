<?php

declare(strict_types=1);

use App\Modules\Memberships\Presentation\CreateMembershipTypeController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/business')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::post('/membership-types', CreateMembershipTypeController::class);
    });
