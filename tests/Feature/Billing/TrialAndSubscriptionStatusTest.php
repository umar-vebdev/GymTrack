<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\User;
use App\Modules\Billing\Domain\BillingPeriod;
use App\Modules\Billing\Domain\PlatformPlanRepositoryInterface;
use App\Shared\Domain\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class TrialAndSubscriptionStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_registration_automatically_starts_14_day_trial_and_staff_can_view_status_and_plans(): void
    {
        /** @var PlatformPlanRepositoryInterface $plans */
        $plans = $this->app->make(PlatformPlanRepositoryInterface::class);
        $plans->create('Standard Monthly', BillingPeriod::Month, new Money(30000, 'TJS'), maxGyms: 1, maxStaff: 5);
        $plans->create('Archived Plan', BillingPeriod::Month, new Money(10000, 'TJS'), maxGyms: 1, maxStaff: 2, isActive: false);

        $owner = User::factory()->create();
        Sanctum::actingAs($owner);

        $reg = $this->postJson('/api/v1/business/companies', [
            'company_name' => 'Alpha Fitness',
            'gym_name' => 'Alpha Main',
        ])->assertCreated();

        $companyId = (int) $reg->json('data.company_id');

        $this->assertDatabaseHas('billing_subscriptions', [
            'company_id' => $companyId,
            'status' => 'trial',
            'plan_id' => null,
        ]);

        $subRes = $this->getJson('/api/v1/business/billing/subscription');
        $subRes->assertOk()
            ->assertJsonPath('data.company_id', $companyId)
            ->assertJsonPath('data.status', 'trial')
            ->assertJsonPath('data.is_read_only', false)
            ->assertJsonPath('data.plan_id', null);

        $plansRes = $this->getJson('/api/v1/business/billing/plans');
        $plansRes->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Standard Monthly')
            ->assertJsonPath('data.0.price_minor', 30000);
    }

    public function test_non_staff_cannot_view_company_subscription(): void
    {
        $stranger = User::factory()->create();
        Sanctum::actingAs($stranger);

        $this->getJson('/api/v1/business/billing/subscription')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }
}

