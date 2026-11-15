<?php

declare(strict_types=1);

use App\Http\Controllers\HealthCheckController;
use App\Modules\Identity\Presentation\LoginUserController;
use App\Modules\Identity\Presentation\LogoutUserController;
use App\Modules\Identity\Presentation\RegisterUserController;
use App\Modules\Identity\Presentation\ResetPasswordController;
use App\Modules\Identity\Presentation\SendOtpController;
use App\Modules\Identity\Presentation\VerifyEmailController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/register', RegisterUserController::class)->middleware('throttle:auth-register');
    Route::post('/auth/login', LoginUserController::class)->middleware('throttle:auth-login');
    Route::post('/auth/send-otp', SendOtpController::class)->middleware('throttle:auth-otp-send');
    Route::post('/auth/verify-email', VerifyEmailController::class)->middleware('throttle:auth-otp-verify');
    Route::post('/auth/reset-password', ResetPasswordController::class)->middleware('throttle:auth-otp-verify');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', LogoutUserController::class);
        Route::get('/user', function (Request $request) {
            return $request->user();
        });
    });

    Route::get('/health', HealthCheckController::class);
});
