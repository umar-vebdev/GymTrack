<?php

declare(strict_types=1);

use App\Modules\Companies\Domain\Gym;

it('uses Asia/Dushanbe as default timezone', function (): void {
    $gym = new Gym(
        id: 1,
        companyId: 10,
        name: 'Tiger Gym',
        address: 'ул. Рудаки, 1',
    );

    expect($gym->timezone())->toBe('Asia/Dushanbe');
});

it('accepts valid IANA timezone', function (): void {
    $gym = new Gym(
        id: 1,
        companyId: 10,
        name: 'Moscow Gym',
        address: null,
        timezone: 'Europe/Moscow',
    );

    expect($gym->timezone())->toBe('Europe/Moscow');
});

it('rejects invalid timezone', function (string $invalid): void {
    new Gym(
        id: 1,
        companyId: 10,
        name: 'Bad Gym',
        address: null,
        timezone: $invalid,
    );
})->with(['', 'Invalid/Zone', 'UTC+5'])->throws(InvalidArgumentException::class);
