<?php

declare(strict_types=1);

use App\Modules\Identity\Presentation\RegisterClientController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/client')->group(function () {
    Route::post('/auth/register', RegisterClientController::class);
});
