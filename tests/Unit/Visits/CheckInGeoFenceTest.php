<?php

declare(strict_types=1);

use App\Modules\Companies\Domain\Gym;
use App\Modules\Visits\Domain\Exceptions\CheckInDenied;
use App\Modules\Visits\Domain\Rules\CheckInContext;
use App\Modules\Visits\Domain\Rules\WithinGymGeoFenceRule;

function geoContext(
    bool $enabled = true,
    int $radiusMeters = 200,
    ?float $gymLat = 38.5598,
    ?float $gymLng = 68.7870,
    ?float $clientLat = 38.5598,
    ?float $clientLng = 68.7870,
): CheckInContext {
    return new CheckInContext(
        companyId: 1,
        gymId: 1,
        clientId: 1,
        now: new DateTimeImmutable('2026-11-15 10:00:00', new DateTimeZone('UTC')),
        remainingVisits: 5,
        membershipExpiresAt: null,
        membershipFrozen: false,
        dailyLimit: 1,
        timeFromMinute: null,
        timeToMinute: null,
        gymTimezone: 'Asia/Dushanbe',
        geoCheckEnabled: $enabled,
        geoCheckRadiusMeters: $radiusMeters,
        gymLatitude: $gymLat,
        gymLongitude: $gymLng,
        clientLatitude: $clientLat,
        clientLongitude: $clientLng,
    );
}

test('geofence rule skips when disabled or gym has no coordinates', function (): void {
    $rule = new WithinGymGeoFenceRule;

    $rule->validate(geoContext(enabled: false, clientLat: null, clientLng: null));
    $rule->validate(geoContext(enabled: true, gymLat: null, gymLng: null, clientLat: null, clientLng: null));
    $rule->validate(geoContext(enabled: true, gymLat: 38.5598, gymLng: null, clientLat: null, clientLng: null));

    expect(true)->toBeTrue();
});

test('geofence rule requires client coordinates when enabled and gym coordinates are set', function (): void {
    $rule = new WithinGymGeoFenceRule;

    expect(fn () => $rule->validate(geoContext(enabled: true, clientLat: null, clientLng: 68.7870)))
        ->toThrow(CheckInDenied::class, CheckInDenied::REASON_GEO_LOCATION_REQUIRED);

    expect(fn () => $rule->validate(geoContext(enabled: true, clientLat: 38.5598, clientLng: null)))
        ->toThrow(CheckInDenied::class, CheckInDenied::REASON_GEO_LOCATION_REQUIRED);
});

test('geofence rule passes within radius and denies outside radius', function (): void {
    $rule = new WithinGymGeoFenceRule;

    // ~55 метров на север (1 градус широты ≈ 111.19 км, 0.0005° ≈ 55.6 м)
    $rule->validate(geoContext(enabled: true, radiusMeters: 100, clientLat: 38.5603, clientLng: 68.7870));

    // ~556 метров на север (0.005° ≈ 556 м > 200 м)
    expect(fn () => $rule->validate(geoContext(enabled: true, radiusMeters: 200, clientLat: 38.5648, clientLng: 68.7870)))
        ->toThrow(CheckInDenied::class, CheckInDenied::REASON_OUTSIDE_GYM_GEOFENCE);
});

test('gym entity validates geofence radius boundaries', function (): void {
    $gym = new Gym(
        id: 1,
        companyId: 10,
        name: 'Geo Gym',
        address: 'ул. Рудаки, 1',
        geoCheckEnabled: true,
        geoCheckRadiusMeters: 50,
    );

    expect($gym->geoCheckEnabled())->toBeTrue()
        ->and($gym->geoCheckRadiusMeters())->toBe(50);

    $updated = $gym->withDetails('Geo Gym', null, 'Asia/Dushanbe', false, 5000);
    expect($updated->geoCheckEnabled())->toBeFalse()
        ->and($updated->geoCheckRadiusMeters())->toBe(5000);

    expect(fn () => new Gym(1, 10, 'Bad', null, geoCheckRadiusMeters: 49))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => new Gym(1, 10, 'Bad', null, geoCheckRadiusMeters: 5001))
        ->toThrow(InvalidArgumentException::class);
});
