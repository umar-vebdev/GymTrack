<?php

declare(strict_types=1);

namespace Tests\Unit\Billing;

use App\Modules\Billing\Domain\BillingPeriod;
use App\Modules\Billing\Domain\PlatformPlan;
use App\Modules\Billing\Domain\PlatformPlanRepositoryInterface;
use App\Shared\Domain\Money;
use DateTimeImmutable;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PlatformPlanRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function repo(): PlatformPlanRepositoryInterface
    {
        return $this->app->make(PlatformPlanRepositoryInterface::class);
    }

    public function test_it_creates_reads_lists_and_toggles_platform_plan(): void
    {
        $monthlyId = $this->repo()->create(
            name: 'Standard Monthly',
            period: BillingPeriod::Month,
            price: new Money(30000, 'TJS'),
            maxGyms: 1,
            maxStaff: 5
        );

        $yearlyId = $this->repo()->create(
            name: 'Pro Yearly',
            period: BillingPeriod::Year,
            price: new Money(300000, 'TJS'),
            maxGyms: 5,
            maxStaff: 25
        );

        $monthly = $this->repo()->findById($monthlyId);
        $this->assertNotNull($monthly);
        $this->assertSame('Standard Monthly', $monthly->name());
        $this->assertSame(BillingPeriod::Month, $monthly->period());
        $this->assertSame(30000, $monthly->price()->amount());
        $this->assertSame(1, $monthly->maxGyms());
        $this->assertSame(5, $monthly->maxStaff());
        $this->assertTrue($monthly->isActive());

        $monthly->setActive(false);
        $this->repo()->save($monthly);

        $this->assertCount(2, $this->repo()->listAll(onlyActive: false));
        $activePlans = $this->repo()->listAll(onlyActive: true);
        $this->assertCount(1, $activePlans);
        $this->assertSame($yearlyId, $activePlans[0]->id());
    }

    public function test_billing_period_extends_date_and_plan_validates_invariants(): void
    {
        $from = new DateTimeImmutable('2026-01-15T10:00:00Z');
        $this->assertSame('2026-02-15', BillingPeriod::Month->extendDate($from)->format('Y-m-d'));
        $this->assertSame('2027-01-15', BillingPeriod::Year->extendDate($from)->format('Y-m-d'));

        $this->expectException(DomainException::class);
        new PlatformPlan(
            id: 1,
            name: 'Invalid',
            period: BillingPeriod::Month,
            price: new Money(1000, 'TJS'),
            maxGyms: 0,
            maxStaff: 1
        );
    }
}

