<?php

declare(strict_types=1);

use App\Modules\Schedule\Presentation\CreateClassTemplateController;
use App\Modules\Schedule\Presentation\ListClassTemplatesController;
use App\Modules\Schedule\Presentation\UpdateClassTemplateController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/business/schedule')
    ->middleware('auth:sanctum')
    ->group(function (): void {
        Route::get('/templates', ListClassTemplatesController::class);
        Route::post('/templates', CreateClassTemplateController::class);
        Route::patch('/templates/{template}', UpdateClassTemplateController::class)->whereNumber('template');
    });
