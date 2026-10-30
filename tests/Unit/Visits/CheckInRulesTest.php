<?php

declare(strict_types=1);

use App\Modules\Visits\Domain\Exceptions\CheckInDenied;
use App\Modules\Visits\Domain\Rules\CheckInContext;
use App\Modules\Visits\Domain\Rules\DailyLimitNotExceededRule;
use App\Modules\Visits\Domain\Rules\MembershipIsAvailableRule;
use App\Modules\Visits\Domain\Rules\NotFrozenRule;
use App\Modules\Visits\Domain\Rules\WithinAllowedTimeRule;
use Tests\Unit\Visits\Support\FakeVisitRepository;

function checkInContext(
    ?int $remaining = 5,
    ?DateTimeImmutable $expiresAt = null,
    bool $frozen = false,
    string $now = '2026-10-30 12:00:00',
): CheckInContext {
    return new CheckInContext(
        companyId: 1,
        gymId: 1,
        clientId: 1,
        now: new DateTimeImmutable($now),
        remainingVisits: $remaining,
        membershipExpiresAt: $expiresAt,
        membershipFrozen: $frozen,
    );
}

function denialReason(callable $fn): ?string
{
    try {
        $fn();
    } catch (CheckInDenied $e) {
        return $e->getMessage();
    }

    return null;
}

test('membership rule passes with visits left and no expiry', function () {
    expect(denialReason(fn () => (new MembershipIsAvailableRule)->validate(checkInContext())))->toBeNull();
});

test('membership rule passes for unlimited visits', function () {
    expect(denialReason(fn () => (new MembershipIsAvailableRule)->validate(checkInContext(remaining: null))))->toBeNull();
});

test('membership rule denies when no visits left', function () {
    expect(denialReason(fn () => (new MembershipIsAvailableRule)->validate(checkInContext(remaining: 0))))
        ->toContain(CheckInDenied::REASON_NO_ACTIVE_MEMBERSHIP);
});

test('membership rule denies when expired', function () {
    $ctx = checkInContext(expiresAt: new DateTimeImmutable('2026-09-01 00:00:00'));

    expect(denialReason(fn () => (new MembershipIsAvailableRule)->validate($ctx)))
        ->toContain(CheckInDenied::REASON_NO_ACTIVE_MEMBERSHIP);
});

test('not frozen rule passes and denies', function () {
    expect(denialReason(fn () => (new NotFrozenRule)->validate(checkInContext())))->toBeNull();
    expect(denialReason(fn () => (new NotFrozenRule)->validate(checkInContext(frozen: true))))
        ->toContain(CheckInDenied::REASON_FROZEN);
});

test('time rule allows opening hours and denies outside them', function () {
    $rule = new WithinAllowedTimeRule;

    expect(denialReason(fn () => $rule->validate(checkInContext(now: '2026-10-30 06:00:00'))))->toBeNull();
    expect(denialReason(fn () => $rule->validate(checkInContext(now: '2026-10-30 21:59:00'))))->toBeNull();
    expect(denialReason(fn () => $rule->validate(checkInContext(now: '2026-10-30 05:59:00'))))
        ->toContain(CheckInDenied::REASON_OUT_OF_TIME_WINDOW);
    expect(denialReason(fn () => $rule->validate(checkInContext(now: '2026-10-30 22:00:00'))))
        ->toContain(CheckInDenied::REASON_OUT_OF_TIME_WINDOW);
});

test('daily limit rule passes under limit and denies at limit', function () {
    expect(denialReason(fn () => (new DailyLimitNotExceededRule(new FakeVisitRepository(1)))->validate(checkInContext())))
        ->toBeNull();
    expect(denialReason(fn () => (new DailyLimitNotExceededRule(new FakeVisitRepository(2)))->validate(checkInContext())))
        ->toContain(CheckInDenied::REASON_DAILY_LIMIT_EXCEEDED);
});
