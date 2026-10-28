<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\User;
use App\Modules\Billing\Domain\BillingPeriod;
use App\Modules\Billing\Domain\PlatformPlanRepositoryInterface;
use App\Modules\Billing\Domain\SubscriptionRepositoryInterface;
use App\Modules\Companies\Infrastructure\Persistence\CompanyModel;
use App\Shared\Domain\Money;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class ActivateAndExpireSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_activates_company_subscription_and_writes_audit_while_regular_user_gets_403(): void
    {
        /** @var PlatformPlanRepositoryInterface $plans */
        $plans = $this->app->make(PlatformPlanRepositoryInterface::class);
        $planId = $plans->create('Standard Monthly', BillingPeriod::Month, new Money(30000, 'TJS'), maxGyms: 1, maxStaff: 5);

        $owner = User::factory()->create();
        Sanctum::actingAs($owner);

        $reg = $this->postJson('/api/v1/business/companies', [
            'company_name' => 'Alpha Fitness',
            'gym_name' => 'Alpha Main',
        ])->assertCreated();

        $companyId = (int) $reg->json('data.company_id');

        // Owner (not platform admin) cannot activate platform subscription directly
        $this->postJson("/api/v1/platform/companies/{$companyId}/subscription/activate", [
            'plan_id' => $planId,
        ])->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');

        // Platform admin activates subscription
        $platformAdmin = User::factory()->create();
        $platformAdmin->is_platform_admin = true;
        $platformAdmin->save();

        Sanctum::actingAs($platformAdmin);

        $res = $this->postJson("/api/v1/platform/companies/{$companyId}/subscription/activate", [
            'plan_id' => $planId,
        ]);

        $res->assertOk()
            ->assertJsonPath('data.company_id', $companyId)
            ->assertJsonPath('data.plan_id', $planId)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.is_read_only', false);

        $this->assertDatabaseHas('audit_entries', [
            'company_id' => $companyId,
            'user_id' => $platformAdmin->id,
            'action' => 'SUBSCRIPTION_ACTIVATED',
            'entity_type' => 'Subscription',
        ]);
    }

    public function test_billing_expire_due_command_marks_overdue_subscriptions_as_expired_and_is_idempotent(): void
    {
        $owner = User::factory()->create();
        $companyId = CompanyModel::create([
            'owner_id' => $owner->id,
            'name' => 'Expired Gym',
        ])->id;

        /** @var SubscriptionRepositoryInterface $subscriptions */
        $subscriptions = $this->app->make(SubscriptionRepositoryInterface::class);
        $subscriptions->createTrial(
            companyId: $companyId,
            startsAt: new DateTimeImmutable('-20 days'),
            endsAt: new DateTimeImmutable('-6 days')
        );

        $exitCode = Artisan::call('billing:expire-due');
        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Переведено истёкших подписок в expired: 1', Artisan::output());

        $this->assertDatabaseHas('billing_subscriptions', [
            'company_id' => $companyId,
            'status' => 'expired',
        ]);

        // Second run is idempotent (0 expired)
        Artisan::call('billing:expire-due');
        $this->assertStringContainsString('Переведено истёкших подписок в expired: 0', Artisan::output());
    }
}
