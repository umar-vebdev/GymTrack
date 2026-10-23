<?php

declare(strict_types=1);

namespace Tests\Unit\Memberships\Strategies;

use App\Modules\Memberships\Domain\Exceptions\NoVisitsLeft;
use App\Modules\Memberships\Domain\Membership;
use App\Modules\Memberships\Domain\MembershipKind;
use App\Modules\Memberships\Domain\MembershipRules;
use App\Modules\Memberships\Domain\MembershipStatus;
use App\Modules\Memberships\Domain\StartMode;
use App\Modules\Memberships\Domain\Strategies\ConsumptionStrategyResolver;
use App\Modules\Memberships\Domain\Strategies\TimeBasedConsumptionStrategy;
use App\Modules\Memberships\Domain\Strategies\VisitsConsumptionStrategy;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ConsumptionStrategiesTest extends TestCase
{
    public function test_resolver_returns_correct_strategy(): void
    {
        $this->assertInstanceOf(
            VisitsConsumptionStrategy::class,
            ConsumptionStrategyResolver::resolve(MembershipKind::Visits)
        );

        $this->assertInstanceOf(
            TimeBasedConsumptionStrategy::class,
            ConsumptionStrategyResolver::resolve(MembershipKind::Duration)
        );

        $this->assertInstanceOf(
            TimeBasedConsumptionStrategy::class,
            ConsumptionStrategyResolver::resolve(MembershipKind::Unlimited)
        );

        $this->assertInstanceOf(
            TimeBasedConsumptionStrategy::class,
            ConsumptionStrategyResolver::resolve(MembershipKind::Single)
        );
    }

    public function test_visits_strategy_decrements_and_expires(): void
    {
        $rules = MembershipRules::create(
            kind: MembershipKind::Visits,
            startMode: StartMode::OnSale,
            visitsCount: 1,
            validityDays: 30
        );

        $membership = new Membership(
            id: 1,
            companyId: 10,
            clientId: 100,
            typeId: 5,
            rules: $rules,
            status: MembershipStatus::Active,
            remainingVisits: 1,
            startsAt: new DateTimeImmutable('2026-10-01 10:00:00'),
            endsAt: new DateTimeImmutable('2026-10-31 23:59:59')
        );

        $strategy = new VisitsConsumptionStrategy;
        $strategy->consume($membership, new DateTimeImmutable('2026-10-02 12:00:00'));

        $this->assertSame(0, $membership->remainingVisits());
        $this->assertSame(MembershipStatus::Expired, $membership->status());

        $this->expectException(NoVisitsLeft::class);
        $strategy->consume($membership, new DateTimeImmutable('2026-10-02 13:00:00'));
    }

    public function test_time_based_strategy_does_not_modify_visits(): void
    {
        $rules = MembershipRules::create(
            kind: MembershipKind::Unlimited,
            startMode: StartMode::OnSale,
            durationDays: 30
        );

        $membership = new Membership(
            id: 2,
            companyId: 10,
            clientId: 100,
            typeId: 6,
            rules: $rules,
            status: MembershipStatus::Active,
            remainingVisits: null,
            startsAt: new DateTimeImmutable('2026-10-01 10:00:00'),
            endsAt: new DateTimeImmutable('2026-10-31 23:59:59')
        );

        $strategy = new TimeBasedConsumptionStrategy;
        $strategy->consume($membership, new DateTimeImmutable('2026-10-02 12:00:00'));

        $this->assertNull($membership->remainingVisits());
        $this->assertSame(MembershipStatus::Active, $membership->status());
    }
}
