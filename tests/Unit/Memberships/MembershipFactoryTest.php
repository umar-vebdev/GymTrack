<?php

declare(strict_types=1);

namespace Tests\Unit\Memberships;

use App\Modules\Memberships\Domain\MembershipFactory;
use App\Modules\Memberships\Domain\MembershipKind;
use App\Modules\Memberships\Domain\MembershipRules;
use App\Modules\Memberships\Domain\MembershipStatus;
use App\Modules\Memberships\Domain\MembershipType;
use App\Modules\Memberships\Domain\StartMode;
use App\Shared\Domain\Money;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class MembershipFactoryTest extends TestCase
{
    private function type(MembershipRules $rules): MembershipType
    {
        return new MembershipType(1, 10, 'Type', new Money(1000, 'TJS'), $rules, true);
    }

    public function test_visits_on_sale_is_active_with_validity(): void
    {
        $at = new DateTimeImmutable('2026-10-01 10:00:00');
        $rules = MembershipRules::create(MembershipKind::Visits, StartMode::OnSale, visitsCount: 12, validityDays: 60);

        $terms = MembershipFactory::termsFor($this->type($rules), $at);

        $this->assertSame(MembershipStatus::Active, $terms->status);
        $this->assertSame(12, $terms->remainingVisits);
        $this->assertSame($at, $terms->startsAt);
        $this->assertSame('2026-11-30 10:00:00', $terms->endsAt?->format('Y-m-d H:i:s'));
    }

    public function test_visits_without_validity_has_no_end(): void
    {
        $rules = MembershipRules::create(MembershipKind::Visits, StartMode::OnSale, visitsCount: 5);

        $terms = MembershipFactory::termsFor($this->type($rules), new DateTimeImmutable('2026-10-01 10:00:00'));

        $this->assertNull($terms->endsAt);
    }

    public function test_duration_on_sale(): void
    {
        $rules = MembershipRules::create(MembershipKind::Duration, StartMode::OnSale, durationDays: 30);

        $terms = MembershipFactory::termsFor($this->type($rules), new DateTimeImmutable('2026-10-01 10:00:00'));

        $this->assertSame(MembershipStatus::Active, $terms->status);
        $this->assertNull($terms->remainingVisits);
        $this->assertSame('2026-10-31 10:00:00', $terms->endsAt?->format('Y-m-d H:i:s'));
    }

    public function test_first_visit_mode_is_pending_without_dates(): void
    {
        $rules = MembershipRules::create(MembershipKind::Unlimited, StartMode::FirstVisit, durationDays: 365);

        $terms = MembershipFactory::termsFor($this->type($rules), new DateTimeImmutable('2026-10-01 10:00:00'));

        $this->assertSame(MembershipStatus::Pending, $terms->status);
        $this->assertNull($terms->startsAt);
        $this->assertNull($terms->endsAt);
    }

    public function test_single_visit_ends_at_end_of_day(): void
    {
        $rules = MembershipRules::create(MembershipKind::Single, StartMode::OnSale);

        $terms = MembershipFactory::termsFor($this->type($rules), new DateTimeImmutable('2026-10-01 10:00:00'));

        $this->assertSame(MembershipStatus::Active, $terms->status);
        $this->assertSame('2026-10-01 23:59:59', $terms->endsAt?->format('Y-m-d H:i:s'));
    }
}
