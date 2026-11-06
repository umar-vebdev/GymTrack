<?php

declare(strict_types=1);

use App\Modules\Catalog\Presentation\ListPublicGymsController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/public')->group(function (): void {
    Route::get('/gyms', ListPublicGymsController::class);
});
