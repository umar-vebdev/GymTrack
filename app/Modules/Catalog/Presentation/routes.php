<?php

declare(strict_types=1);

use App\Modules\Catalog\Presentation\ListPublicGymReviewsController;
use App\Modules\Catalog\Presentation\ListPublicGymScheduleController;
use App\Modules\Catalog\Presentation\ListPublicGymsController;
use App\Modules\Catalog\Presentation\ShowPublicGymController;
use App\Modules\Catalog\Presentation\UpsertGymReviewController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/public')->group(function (): void {
    Route::get('/gyms', ListPublicGymsController::class);
    Route::get('/gyms/{publicId}', ShowPublicGymController::class);
    Route::get('/gyms/{publicId}/schedule', ListPublicGymScheduleController::class);
    Route::get('/gyms/{publicId}/reviews', ListPublicGymReviewsController::class);
});

Route::prefix('api/v1/client')
    ->middleware(['auth:sanctum'])
    ->group(function (): void {
        Route::put('/gyms/{publicId}/review', UpsertGymReviewController::class);
    });
