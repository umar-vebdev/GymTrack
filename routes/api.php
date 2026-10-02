<?php

declare(strict_types=1);

use App\Http\Controllers\HealthCheckController;
use App\Modules\Identity\Presentation\RegisterUserController;
use App\Modules\Identity\Presentation\SendOtpController;
use App\Modules\Identity\Presentation\VerifyPhoneController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/register', RegisterUserController::class);
    Route::post('/auth/send-otp', SendOtpController::class);
    Route::post('/auth/verify-phone', VerifyPhoneController::class);

    Route::get('/health', HealthCheckController::class);

    Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
        return $request->user();
    });
});
