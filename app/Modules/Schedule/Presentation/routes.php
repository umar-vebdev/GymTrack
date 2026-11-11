<?php

declare(strict_types=1);

use App\Modules\Schedule\Presentation\CancelClassSessionController;
use App\Modules\Schedule\Presentation\CreateClassTemplateController;
use App\Modules\Schedule\Presentation\GenerateClassSessionsController;
use App\Modules\Schedule\Presentation\ListClassSessionsController;
use App\Modules\Schedule\Presentation\ListClassTemplatesController;
use App\Modules\Schedule\Presentation\ListClientGymScheduleController;
use App\Modules\Schedule\Presentation\UpdateClassTemplateController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/business/schedule')
    ->middleware('auth:sanctum')
    ->group(function (): void {
        Route::get('/templates', ListClassTemplatesController::class);
        Route::post('/templates', CreateClassTemplateController::class);
        Route::patch('/templates/{template}', UpdateClassTemplateController::class)->whereNumber('template');

        Route::get('/sessions', ListClassSessionsController::class);
        Route::post('/sessions/generate', GenerateClassSessionsController::class);
        Route::post('/sessions/{session}/cancel', CancelClassSessionController::class)->whereNumber('session');
    });

Route::prefix('api/v1/client')
    ->middleware('auth:sanctum')
    ->group(function (): void {
        Route::get('/gyms/{gym}/schedule', ListClientGymScheduleController::class)->whereNumber('gym');
    });
