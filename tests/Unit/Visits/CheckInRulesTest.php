<?php

declare(strict_types=1);

use App\Modules\Memberships\Contracts\CheckInMembershipCandidate;
use App\Modules\Visits\Domain\Exceptions\CheckInDenied;
use App\Modules\Visits\Domain\Rules\CheckInContext;
use App\Modules\Visits\Domain\Rules\DailyLimitNotExceededRule;
use App\Modules\Visits\Domain\Rules\MembershipIsAvailableRule;
use App\Modules\Visits\Domain\Rules\NotFrozenRule;
use App\Modules\Visits\Domain\Rules\WithinAllowedTimeRule;
use App\Modules\Visits\Domain\VisitRepositoryInterface;
use Mockery;

/**
 * @covers \App\Modules\Visits\Domain\Rules\MembershipIsAvailableRule
 * @covers \App\Modules\Visits\Domain\Rules\NotFrozenRule
 * @covers \App\Modules\Visits\Domain\Rules\WithinAllowedTimeRule
 * @covers \App\Modules\Visits\Domain\Rules\DailyLimitNotExceededRule
 */
test('MembershipIsAvailableRule passes when candidate has visits left and not expired', function () {
    $candidate = new CheckInMembershipCandidate(
        membershipId: 1,
        remainingVisits: 5,
        expiresAt: null,
        isFrozen: false,
    );
    $ctx = new CheckInContext(
        companyId: 1,
        gymId: 1,
        clientId: 1,
        now: new DateTimeImmutable('2026-10-30 12:00:00'),
        candidate: $candidate,
    );
    (new MembershipIsAvailableRule)->validate($ctx);
    expect(true)->toBeTrue(); // no exception means pass
});

test('MembershipIsAvailableRule fails when no remaining visits', function () {
    $candidate = new CheckInMembershipCandidate(
        membershipId: 2,
        remainingVisits: 0,
        expiresAt: null,
        isFrozen: false,
    );
    $ctx = new CheckInContext(
        companyId: 1,
        gymId: 1,
        clientId: 1,
        now: new DateTimeImmutable('2026-10-30 12:00:00'),
        candidate: $candidate,
    );
    expect(fn () => (new MembershipIsAvailableRule)->validate($ctx))
        ->toThrow(CheckInDenied::class);
});

test('MembershipIsAvailableRule fails when expired', function () {
    $candidate = new CheckInMembershipCandidate(
        membershipId: 3,
        remainingVisits: 5,
        expiresAt: new DateTimeImmutable('2026-09-01 00:00:00'),
        isFrozen: false,
    );
    $ctx = new CheckInContext(
        companyId: 1,
        gymId: 1,
        clientId: 1,
        now: new DateTimeImmutable('2026-10-30 12:00:00'),
        candidate: $candidate,
    );
    expect(fn () => (new MembershipIsAvailableRule)->validate($ctx))
        ->toThrow(CheckInDenied::class);
});

test('NotFrozenRule passes when not frozen', function () {
    $candidate = new CheckInMembershipCandidate(
        membershipId: 4,
        remainingVisits: null,
        expiresAt: null,
        isFrozen: false,
    );
    $ctx = new CheckInContext(
        companyId: 1,
        gymId: 1,
        clientId: 1,
        now: new DateTimeImmutable('2026-10-30 12:00:00'),
        candidate: $candidate,
    );
    (new NotFrozenRule)->validate($ctx);
    expect(true)->toBeTrue();
});

test('NotFrozenRule fails when frozen', function () {
    $candidate = new CheckInMembershipCandidate(
        membershipId: 5,
        remainingVisits: null,
        expiresAt: null,
        isFrozen: true,
    );
    $ctx = new CheckInContext(
        companyId: 1,
        gymId: 1,
        clientId: 1,
        now: new DateTimeImmutable('2026-10-30 12:00:00'),
        candidate: $candidate,
    );
    expect(fn () => (new NotFrozenRule)->validate($ctx))
        ->toThrow(CheckInDenied::class);
});

test('WithinAllowedTimeRule passes during opening hours', function () {
    $candidate = new CheckInMembershipCandidate(1, null, null, false);
    $ctx = new CheckInContext(
        companyId: 1,
        gymId: 1,
        clientId: 1,
        now: new DateTimeImmutable('2026-10-30 10:00:00'), // 10:00 falls within 06‑22
        candidate: $candidate,
    );
    (new WithinAllowedTimeRule)->validate($ctx);
    expect(true)->toBeTrue();
});

test('WithinAllowedTimeRule fails outside opening hours', function () {
    $candidate = new CheckInMembershipCandidate(1, null, null, false);
    $ctx = new CheckInContext(
        companyId: 1,
        gymId: 1,
        clientId: 1,
        now: new DateTimeImmutable('2026-10-30 23:00:00'), // after 22:00
        candidate: $candidate,
    );
    expect(fn () => (new WithinAllowedTimeRule)->validate($ctx))
        ->toThrow(CheckInDenied::class);
});

test('DailyLimitNotExceededRule passes when under limit', function () {
    $repo = Mockery::mock(VisitRepositoryInterface::class);
    $repo->shouldReceive('countCompletedForClientInGymOnDate')
        ->with(1, 1, '2026-10-30')
        ->andReturn(1);
    $candidate = new CheckInMembershipCandidate(1, null, null, false);
    $ctx = new CheckInContext(
        companyId: 1,
        gymId: 1,
        clientId: 1,
        now: new DateTimeImmutable('2026-10-30 12:00:00'),
        candidate: $candidate,
    );
    (new DailyLimitNotExceededRule($repo))->validate($ctx);
    expect(true)->toBeTrue();
});

test('DailyLimitNotExceededRule fails when limit reached', function () {
    $repo = Mockery::mock(VisitRepositoryInterface::class);
    $repo->shouldReceive('countCompletedForClientInGymOnDate')
        ->with(1, 1, '2026-10-30')
        ->andReturn(2);
    $candidate = new CheckInMembershipCandidate(1, null, null, false);
    $ctx = new CheckInContext(
        companyId: 1,
        gymId: 1,
        clientId: 1,
        now: new DateTimeImmutable('2026-10-30 12:00:00'),
        candidate: $candidate,
    );
    expect(fn () => (new DailyLimitNotExceededRule($repo))->validate($ctx))
        ->toThrow(CheckInDenied::class);
});
