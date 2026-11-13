<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Companies\Contracts\GymLookupInterface;
use App\Modules\Companies\Contracts\GymQrLookupInterface;
use App\Modules\Companies\Domain\CompanyRepositoryInterface;
use App\Modules\Companies\Domain\Gym;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('stores and retrieves gym timezone with Asia/Dushanbe default', function (): void {
    $owner = User::factory()->create([
        'phone' => '+992900112233',
    ]);

    /** @var CompanyRepositoryInterface $companies */
    $companies = app(CompanyRepositoryInterface::class);
    $companyId = $companies->create($owner->id, 'FitCorp');

    /** @var GymRepositoryInterface $gyms */
    $gyms = app(GymRepositoryInterface::class);
    $defaultGymId = $gyms->create($companyId, 'Main Gym', 'Rudaki 1');
    $customGymId = $gyms->create($companyId, 'Moscow Gym', 'Tverskaya 2', 'Europe/Moscow');

    $defaultGym = $gyms->findById($defaultGymId);
    $customGym = $gyms->findByIdForUpdate($customGymId);

    expect($defaultGym)->toBeInstanceOf(Gym::class)
        ->and($defaultGym?->timezone())->toBe('Asia/Dushanbe')
        ->and($customGym)->toBeInstanceOf(Gym::class)
        ->and($customGym?->timezone())->toBe('Europe/Moscow');

    /** @var GymLookupInterface $lookup */
    $lookup = app(GymLookupInterface::class);
    expect($lookup->findTimezone($defaultGymId))->toBe('Asia/Dushanbe')
        ->and($lookup->findTimezone($customGymId))->toBe('Europe/Moscow')
        ->and($lookup->findTimezone(999999))->toBeNull();

    /** @var GymQrLookupInterface $qrLookup */
    $qrLookup = app(GymQrLookupInterface::class);
    expect($qrLookup->findById($defaultGymId)?->timezone)->toBe('Asia/Dushanbe')
        ->and($qrLookup->findById($customGymId)?->timezone)->toBe('Europe/Moscow');
});
