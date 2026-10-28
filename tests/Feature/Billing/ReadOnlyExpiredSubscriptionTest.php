<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\User;
use App\Modules\Billing\Domain\BillingPeriod;
use App\Modules\Billing\Domain\PlatformPlanRepositoryInterface;
use App\Modules\Billing\Infrastructure\Persistence\SubscriptionModel;
use App\Shared\Domain\Money;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class ReadOnlyExpiredSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_trial_switches_business_cabinet_to_read_only_preserving_data_until_plan_activated(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);

        // 1. Register company (starts 14-day trial) and create a product while trial is active
        $reg = $this->postJson('/api/v1/business/companies', [
            'company_name' => 'Titan Gym',
            'gym_name' => 'Titan Central',
        ])->assertCreated();

        $companyId = (int) $reg->json('data.company_id');

        $productRes = $this->postJson('/api/v1/business/products', [
            'name' => 'Water 0.5L',
            'price_minor' => 500,
            'stock_quantity' => 20,
        ])->assertCreated();

        $productId = (int) $productRes->json('data.id');

        // 2. Simulate trial expiration without payment
        SubscriptionModel::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->update([
                'starts_at' => new DateTimeImmutable('-16 days'),
                'ends_at' => new DateTimeImmutable('-2 days'),
            ]);

        // 3. Cabinet is available for reading, saved data is intact
        $this->getJson('/api/v1/business/products')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $productId)
            ->assertJsonPath('data.0.name', 'Water 0.5L');

        $this->getJson('/api/v1/business/sales')
            ->assertOk();

        $this->getJson('/api/v1/business/billing/subscription')
            ->assertOk()
            ->assertJsonPath('data.status', 'expired')
            ->assertJsonPath('data.is_read_only', true);

        // 4. All mutating requests in business cabinet are blocked with 403 SUBSCRIPTION_EXPIRED
        $this->postJson('/api/v1/business/products', [
            'name' => 'Protein Bar',
            'price_minor' => 1500,
        ])->assertForbidden()
            ->assertJsonPath('error.code', 'SUBSCRIPTION_EXPIRED');

        $this->postJson('/api/v1/business/sales', [
            'payment_method' => 'cash',
            'products' => [
                ['product_id' => $productId, 'quantity' => 1],
            ],
        ])->assertForbidden()
            ->assertJsonPath('error.code', 'SUBSCRIPTION_EXPIRED');

        // 5. Platform admin activates subscription -> write access is restored
        /** @var PlatformPlanRepositoryInterface $plans */
        $plans = $this->app->make(PlatformPlanRepositoryInterface::class);
        $planId = $plans->create('Standard Monthly', BillingPeriod::Month, new Money(30000, 'TJS'), maxGyms: 1, maxStaff: 5);

        $platformAdmin = User::factory()->create();
        $platformAdmin->is_platform_admin = true;
        $platformAdmin->save();

        Sanctum::actingAs($platformAdmin);
        $this->postJson("/api/v1/platform/companies/{$companyId}/subscription/activate", [
            'plan_id' => $planId,
        ])->assertOk();

        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/business/sales', [
            'payment_method' => 'cash',
            'products' => [
                ['product_id' => $productId, 'quantity' => 2],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.total_minor', 1000);
    }
}

