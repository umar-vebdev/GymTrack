<?php

declare(strict_types=1);

arch('domain layer does not depend on laravel')
    ->expect('App\Modules\*\Domain')
    ->not->toUse([
        'Illuminate',
        'App\Http',
        'App\Models',
    ]);

arch('shared domain layer does not depend on laravel')
    ->expect('App\Shared\Domain')
    ->not->toUse([
        'Illuminate',
    ]);
