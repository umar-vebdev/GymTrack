<?php

declare(strict_types=1);

use App\Modules\Sales\Presentation\CreateMembershipRequestController;
use App\Modules\Sales\Presentation\CreateProductController;
use App\Modules\Sales\Presentation\ListClientSalesController;
use App\Modules\Sales\Presentation\ListCompanySalesController;
use App\Modules\Sales\Presentation\ListMyMembershipRequestsController;
use App\Modules\Sales\Presentation\ListProductsController;
use App\Modules\Sales\Presentation\RefundSaleController;
use App\Modules\Sales\Presentation\RegisterSaleController;
use App\Modules\Sales\Presentation\UpdateProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/business')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::get('/products', ListProductsController::class);
        Route::post('/products', CreateProductController::class);
        Route::patch('/products/{id}', UpdateProductController::class);
        Route::get('/sales', ListCompanySalesController::class);
        Route::post('/sales', RegisterSaleController::class);
        Route::post('/sales/{id}/refund', RefundSaleController::class);
        Route::get('/clients/{id}/sales', ListClientSalesController::class);
    });

Route::prefix('api/v1/client')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::post('/membership-requests', CreateMembershipRequestController::class);
        Route::get('/membership-requests', ListMyMembershipRequestsController::class);
    });
