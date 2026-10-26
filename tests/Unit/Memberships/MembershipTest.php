<?php

declare(strict_types=1);

namespace Tests\Unit\Memberships;

use App\Modules\Memberships\Domain\Exceptions\MembershipAlreadyCancelled;
use App\Modules\Memberships\Domain\Exceptions\MembershipFreezeNotAllowed;
use App\Modules\Memberships\Domain\Exceptions\MembershipNotUsable;
use App\Modules\Memberships\Domain\Membership;
use App\Modules\Memberships\Domain\MembershipKind;
use App\Modules\Memberships\Domain\MembershipRules;
use App\Modules\Memberships\Domain\MembershipStatus;
use App\Modules\Memberships\Domain\StartMode;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class MembershipTest extends TestCase
{
    public function test_consume_visit_decrements_remaining_and_expires_when_zero(): void
    {
        $rules = MembershipRules::create(
            kind: MembershipKind::Visits,
            startMode: StartMode::OnSale,
            visitsCount: 2,
            validityDays: 30
        );

        $membership = new Membership(
            id: 1,
            companyId: 10,
            clientId: 100,
            typeId: 5,
            rules: $rules,
            status: MembershipStatus::Active,
            remainingVisits: 2,
            startsAt: new DateTimeImmutable('2026-10-01 10:00:00'),
            endsAt: new DateTimeImmutable('2026-10-31 23:59:59')
        );

        $now = new DateTimeImmutable('2026-10-02 12:00:00');
        $membership->consumeVisit($now);

        $this->assertSame(1, $membership->remainingVisits());
        $this->assertSame(MembershipStatus::Active, $membership->status());

        $membership->consumeVisit($now);
        $this->assertSame(0, $membership->remainingVisits());
        $this->assertSame(MembershipStatus::Expired, $membership->status());

        $this->expectException(MembershipNotUsable::class);
        $membership->consumeVisit($now);
    }

    public function test_cannot_consume_expired_by_time_membership(): void
    {
        $rules = MembershipRules::create(
            kind: MembershipKind::Duration,
            startMode: StartMode::OnSale,
            durationDays: 30
        );

        $membership = new Membership(
            id: 1,
            companyId: 10,
            clientId: 100,
            typeId: 5,
            rules: $rules,
            status: MembershipStatus::Active,
            remainingVisits: null,
            startsAt: new DateTimeImmutable('2026-09-01 00:00:00'),
            endsAt: new DateTimeImmutable('2026-10-01 00:00:00')
        );

        $this->expectException(MembershipNotUsable::class);
        $membership->consumeVisit(new DateTimeImmutable('2026-10-02 10:00:00'));
    }

    public function test_pending_membership_activates_on_first_visit(): void
    {
        $rules = MembershipRules::create(
            kind: MembershipKind::Visits,
            startMode: StartMode::FirstVisit,
            visitsCount: 10,
            validityDays: 30
        );

        $membership = new Membership(
            id: 1,
            companyId: 10,
            clientId: 100,
            typeId: 5,
            rules: $rules,
            status: MembershipStatus::Pending,
            remainingVisits: 10,
            startsAt: null,
            endsAt: null
        );

        $visitTime = new DateTimeImmutable('2026-10-10 09:00:00');
        $membership->consumeVisit($visitTime);

        $this->assertSame(MembershipStatus::Active, $membership->status());
        $this->assertSame($visitTime, $membership->startsAt());
        $this->assertNotNull($membership->endsAt());
        $this->assertSame(9, $membership->remainingVisits());
    }

    public function test_freeze_and_unfreeze_extends_ends_at(): void
    {
        $rules = MembershipRules::create(
            kind: MembershipKind::Duration,
            startMode: StartMode::OnSale,
            durationDays: 30,
            freezeMaxDays: 14,
            freezeMaxTimes: 2
        );

        $endsAt = new DateTimeImmutable('2026-10-31 10:00:00');

        $membership = new Membership(
            id: 1,
            companyId: 10,
            clientId: 100,
            typeId: 5,
            rules: $rules,
            status: MembershipStatus::Active,
            remainingVisits: null,
            startsAt: new DateTimeImmutable('2026-10-01 10:00:00'),
            endsAt: $endsAt
        );

        $frozenAt = new DateTimeImmutable('2026-10-05 10:00:00');
        $membership->freeze($frozenAt);

        $this->assertSame(MembershipStatus::Frozen, $membership->status());
        $this->assertSame(1, $membership->freezeTimesUsed());

        // Attempting to consume while frozen
        try {
            $membership->consumeVisit(new DateTimeImmutable('2026-10-06 10:00:00'));
            $this->fail('Should throw MembershipNotUsable');
        } catch (MembershipNotUsable) {
            // expected
        }

        // Unfreeze 5 days later
        $unfreezeAt = new DateTimeImmutable('2026-10-10 10:00:00');
        $membership->unfreeze($unfreezeAt);

        $this->assertSame(MembershipStatus::Active, $membership->status());
        $this->assertNull($membership->frozenAt());
        $this->assertSame(5, $membership->freezeDaysUsed());
        // EndsAt shifted by 5 days: 2026-10-31 + 5 days = 2026-11-05
        $this->assertSame('2026-11-05 10:00:00', $membership->endsAt()?->format('Y-m-d H:i:s'));
    }

    public function test_freeze_rejects_when_limit_reached_or_not_active(): void
    {
        $rules = MembershipRules::create(
            kind: MembershipKind::Duration,
            startMode: StartMode::OnSale,
            durationDays: 30,
            freezeMaxDays: 7,
            freezeMaxTimes: 1
        );

        $membership = new Membership(
            id: 1,
            companyId: 10,
            clientId: 100,
            typeId: 5,
            rules: $rules,
            status: MembershipStatus::Active,
            remainingVisits: null,
            startsAt: new DateTimeImmutable('2026-10-01 10:00:00'),
            endsAt: new DateTimeImmutable('2026-10-31 10:00:00')
        );

        $membership->freeze(new DateTimeImmutable('2026-10-05 10:00:00'));
        $membership->unfreeze(new DateTimeImmutable('2026-10-07 10:00:00'));

        $this->expectException(MembershipFreezeNotAllowed::class);
        $membership->freeze(new DateTimeImmutable('2026-10-10 10:00:00'));
    }

    public function test_cancel_sets_cancelled_status(): void
    {
        $rules = MembershipRules::create(kind: MembershipKind::Single, startMode: StartMode::OnSale);
        $membership = new Membership(
            id: 1, companyId: 10, clientId: 100, typeId: 5, rules: $rules,
            status: MembershipStatus::Active, remainingVisits: null,
            startsAt: new DateTimeImmutable('2026-10-01 00:00:00'),
            endsAt: new DateTimeImmutable('2026-10-01 23:59:59')
        );

        $membership->cancel('Переезд клиента');
        $this->assertSame(MembershipStatus::Cancelled, $membership->status());
        $this->assertSame('Переезд клиента', $membership->cancellationReason());

        $this->expectException(MembershipAlreadyCancelled::class);
        $membership->cancel('Повторная отмена');
    }

    public function test_is_usable_at_checks_status_expiry_and_visits(): void
    {
        $visits = MembershipRules::create(MembershipKind::Visits, StartMode::OnSale, visitsCount: 5);
        $make = static fn (MembershipStatus $status, ?int $left, ?string $endsAt): Membership => new Membership(
            1, 10, 100, 5, $visits, $status, $left,
            new DateTimeImmutable('2026-10-01 00:00:00'),
            $endsAt === null ? null : new DateTimeImmutable($endsAt)
        );
        $at = new DateTimeImmutable('2026-10-10 12:00:00');

        $this->assertTrue($make(MembershipStatus::Active, 3, '2026-11-01 00:00:00')->isUsableAt($at));
        $this->assertTrue($make(MembershipStatus::Active, 3, null)->isUsableAt($at));
        $this->assertFalse($make(MembershipStatus::Active, 0, '2026-11-01 00:00:00')->isUsableAt($at));
        $this->assertFalse($make(MembershipStatus::Active, 3, '2026-10-05 00:00:00')->isUsableAt($at));
        $this->assertFalse($make(MembershipStatus::Frozen, 3, '2026-11-01 00:00:00')->isUsableAt($at));
        $this->assertFalse($make(MembershipStatus::Cancelled, 3, null)->isUsableAt($at));
    }
}
