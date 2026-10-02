<?php
declare(strict_types=1);

test('domain layer does not depend on laravel')
    ->expect('App\Modules\*\Domain')
    ->not->toUse([
        'Illuminate',
        'App\Http',
        'App\Models',
    ]);

test('shared domain layer does not depend on laravel')
    ->expect('App\Shared')
    ->not->toUse([
        'Illuminate',
    ]);
