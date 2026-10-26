<?php

declare(strict_types=1);

use App\Modules\Memberships\Presentation\CancelMembershipController;
use App\Modules\Memberships\Presentation\CreateMembershipTypeController;
use App\Modules\Memberships\Presentation\FreezeMembershipController;
use App\Modules\Memberships\Presentation\ListClientMembershipsController;
use App\Modules\Memberships\Presentation\ListExpiringMembershipsController;
use App\Modules\Memberships\Presentation\ListMembershipTypesController;
use App\Modules\Memberships\Presentation\SetMembershipTypeActiveController;
use App\Modules\Memberships\Presentation\UnfreezeMembershipController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/business')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::get('/membership-types', ListMembershipTypesController::class);
        Route::post('/membership-types', CreateMembershipTypeController::class);
        Route::patch('/membership-types/{id}/active', SetMembershipTypeActiveController::class);
        Route::get('/clients/{id}/memberships', ListClientMembershipsController::class);
        Route::get('/memberships/expiring', ListExpiringMembershipsController::class);
        Route::post('/memberships/{id}/freeze', FreezeMembershipController::class);
        Route::post('/memberships/{id}/unfreeze', UnfreezeMembershipController::class);
        Route::post('/memberships/{id}/cancel', CancelMembershipController::class);
    });
