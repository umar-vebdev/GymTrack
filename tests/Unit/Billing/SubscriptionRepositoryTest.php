<?php

declare(strict_types=1);

namespace Tests\Unit\Billing;

use App\Models\User;
use App\Modules\Billing\Domain\BillingPeriod;
use App\Modules\Billing\Domain\PlatformPlanRepositoryInterface;
use App\Modules\Billing\Domain\SubscriptionRepositoryInterface;
use App\Modules\Billing\Domain\SubscriptionStatus;
use App\Modules\Billing\Domain\TrialPolicy;
use App\Modules\Companies\Infrastructure\Persistence\CompanyModel;
use App\Shared\Domain\Money;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SubscriptionRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function createCompany(): int
    {
        $owner = User::factory()->create();

        return CompanyModel::create(['owner_id' => $owner->id, 'name' => 'Gym Co'])->id;
    }

    private function subscriptions(): SubscriptionRepositoryInterface
    {
        return $this->app->make(SubscriptionRepositoryInterface::class);
    }

    private function plans(): PlatformPlanRepositoryInterface
    {
        return $this->app->make(PlatformPlanRepositoryInterface::class);
    }

    public function test_trial_policy_creates_14_day_trial_and_subscription_transitions_to_expired_and_active(): void
    {
        $companyId = $this->createCompany();
        $startsAt = new DateTimeImmutable('2026-01-01T00:00:00Z');
        $endsAt = TrialPolicy::trialEndsAt($startsAt);

        $this->assertSame('2026-01-15', $endsAt->format('Y-m-d'));

        $trial = $this->subscriptions()->createTrial($companyId, $startsAt, $endsAt);
        $this->assertSame(SubscriptionStatus::Trial, $trial->status());
        $this->assertFalse($trial->isReadOnlyAt(new DateTimeImmutable('2026-01-10T00:00:00Z')));
        $this->assertTrue($trial->isReadOnlyAt(new DateTimeImmutable('2026-01-16T00:00:00Z')));

        // Expire due
        $due = $this->subscriptions()->listDueForExpiry(new DateTimeImmutable('2026-01-16T00:00:00Z'));
        $this->assertCount(1, $due);
        $this->assertTrue($due[0]->expireIfDue(new DateTimeImmutable('2026-01-16T00:00:00Z')));
        $this->subscriptions()->save($due[0]);

        $reloaded = $this->subscriptions()->findByCompanyId($companyId);
        $this->assertNotNull($reloaded);
        $this->assertSame(SubscriptionStatus::Expired, $reloaded->status());

        // Activate plan
        $planId = $this->plans()->create(
            name: 'Standard Monthly',
            period: BillingPeriod::Month,
            price: new Money(30000, 'TJS'),
            maxGyms: 1,
            maxStaff: 5
        );
        $plan = $this->plans()->findById($planId);
        $this->assertNotNull($plan);

        $activatedAt = new DateTimeImmutable('2026-01-20T00:00:00Z');
        $reloaded->activatePlan($plan, $activatedAt);
        $this->subscriptions()->save($reloaded);

        $active = $this->subscriptions()->findByCompanyId($companyId);
        $this->assertNotNull($active);
        $this->assertSame(SubscriptionStatus::Active, $active->status());
        $this->assertSame($planId, $active->planId());
        $this->assertSame('2026-02-20', $active->endsAt()->format('Y-m-d'));
        $this->assertFalse($active->isReadOnlyAt(new DateTimeImmutable('2026-02-01T00:00:00Z')));
    }
}
