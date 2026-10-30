<?php

declare(strict_types=1);

use App\Modules\Analytics\Presentation\OverviewAnalyticsController;
use App\Modules\Analytics\Presentation\RevenueReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/business')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::get('/analytics/revenue', RevenueReportController::class);
        Route::get('/analytics/overview', OverviewAnalyticsController::class);
    });
